<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\ActivityFile;
use Dcplibrary\Notices\Models\ActivityLog;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Models\JobRun;
use Dcplibrary\Notices\Models\LookupTableOverride;
use Dcplibrary\Notices\Models\NoticesSetting;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationActivityLog;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Models\UiMessage;
use Dcplibrary\Notices\Models\UserActivityLog;
use Dcplibrary\Notices\Services\ActivityLogRetentionSettings;
use Dcplibrary\Notices\Services\NoticesDataBackup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Settings maintenance and backup controller.
 */
class SettingsMaintenanceBackupController extends Controller
{
    public function __construct(protected NoticesDataBackup $backups) {}

    /**
     * Maintenance tools dashboard (e.g. cleanup import/export logs).
     */
    public function maintenance()
    {
        return view('notices::settings.maintenance');
    }

    /**
     * Detailed cleanup tool view with full form.
     *
     * Shows the saved activity-log retention days (default 90) and whether
     * unresolved failures are kept. Does not change any status or delete rows.
     */
    public function maintenanceCleanup(ActivityLogRetentionSettings $settings)
    {
        return view('notices::settings.maintenance-cleanup', [
            'activity_log_days'            => $settings->days(),
            'activity_log_keep_unresolved' => $settings->keepUnresolved(),
        ]);
    }

    /**
     * Save activity-log retention days and the keep-unresolved choice.
     *
     * Does not archive or delete any rows.
     */
    public function saveActivityLogRetention(Request $request, ActivityLogRetentionSettings $settings)
    {
        $saved = $this->storeActivityLogRetention($request, $settings);

        $kept = $saved['keep'] ? ', and keep logs for unresolved failures.' : '.';

        return redirect()
            ->route('notices.settings.maintenance.cleanup')
            ->with('success', 'Saved activity log retention: keep the last ' . $saved['days'] . ' day(s)' . $kept)
        ;
    }

    /**
     * Save the retention settings, then run notices:archive-activity-logs.
     *
     * Deletes archived activity-log rows. Does not change notification status.
     */
    public function runActivityLogArchive(Request $request, ActivityLogRetentionSettings $settings)
    {
        $saved = $this->storeActivityLogRetention($request, $settings);

        $options = [
            '--days'           => $saved['days'],
            '--force'          => true,
            '--no-interaction' => true,
        ];

        if ($saved['keep']) {
            $options['--keep-unresolved'] = true;
        }

        Artisan::call('notices:archive-activity-logs', $options);

        return redirect()
            ->route('notices.settings.maintenance.cleanup')
            ->with('success', 'Archived activity logs older than ' . $saved['days'] . ' day(s). Check the Activity Log for details.')
        ;
    }

    /**
     * Run the notices:cleanup-import-logs command from the web UI.
     *
     * Interprets the `days` field as "days to keep" and deletes cached
     * import/export files older than that cutoff by translating it into a
     * from/to date range (~1 year window) for the console command.
     */
    public function runCleanupImportLogs(Request $request)
    {
        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $days = $data['days'] ?? 30;

        // Interpret the web UI field as "days to keep" and delete files older than
        // that cutoff. We express this to the command as a from/to date range,
        // roughly covering the last year of history.
        $today  = now()->startOfDay();
        $cutoff = (clone $today)->subDays($days); // delete dates <= cutoff
        $from   = (clone $today)->subDays(365);   // safety window of ~1 year

        Artisan::call('notices:cleanup-import-logs', [
            '--from'           => $from->format('Y-m-d'),
            '--to'             => $cutoff->format('Y-m-d'),
            '--force'          => true,
            '--no-interaction' => true,
        ]);

        return redirect()
            ->route('notices.settings.maintenance')
            ->with('success', "Started cleanup of cached import/export files older than {$days} day(s). Check Activity Log for details.")
        ;
    }

    /**
     * Consolidated backup & restore dashboard for configuration and Notices data.
     */
    public function configBackup()
    {
        return $this->backupDashboard();
    }

    /**
     * Create a configuration backup.
     *
     * Supports three delivery mechanisms:
     * - download: stream file to browser
     * - email: email backup as attachment
     * - local: store on server under storage/app/notices/config-backups
     */
    public function createConfigBackup(Request $request)
    {
        $data = $request->validate([
            'mode'        => 'required|string|in:download,email,local',
            'email'       => 'nullable|email',
            'description' => 'nullable|string|max:255',
        ]);

        $payload      = $this->buildConfigBackupPayload($data['description'] ?? null);
        $json         = json_encode($payload, JSON_PRETTY_PRINT);
        $fileName     = 'notices-config-' . now()->format('Ymd_His') . '-' . Str::random(6) . '.json';
        $relativePath = 'notices/config-backups/' . $fileName;

        if ('download' === $data['mode']) {
            return response()->streamDownload(function () use ($json): void {
                echo $json;
            }, $fileName, [
                'Content-Type' => 'application/json',
            ]);
        }

        // For email/local we first persist to disk
        Storage::put($relativePath, $json);

        if ('email' === $data['mode']) {
            $to = $data['email'] ?? config('mail.from.address');

            if (!$to) {
                return redirect()
                    ->route('notices.settings.maintenance.config-backup')
                    ->with('error', 'No email recipient configured. Please provide an email address.')
                ;
            }

            Mail::raw('Attached is a Notices configuration backup generated from the Maintenance tools.', function ($message) use ($to, $relativePath, $fileName): void {
                $message->to($to)
                    ->subject('Notices configuration backup')
                    ->attach(Storage::path($relativePath), [
                        'as'   => $fileName,
                        'mime' => 'application/json',
                    ])
                ;
            });

            return redirect()
                ->route('notices.settings.maintenance.config-backup')
                ->with('success', 'Configuration backup generated and emailed successfully.')
            ;
        }

        // mode === local
        return redirect()
            ->route('notices.settings.maintenance.config-backup')
            ->with('success', 'Configuration backup saved to server at ' . $relativePath . '.')
        ;
    }

    /**
     * Restore configuration settings from an uploaded backup file.
     */
    public function restoreConfigFromUpload(Request $request)
    {
        $data = $request->validate([
            'backup_file' => 'required|file|mimetypes:application/json,text/plain',
        ]);

        $json = file_get_contents($data['backup_file']->getRealPath());

        return $this->restoreConfigFromJson($json, 'Uploaded backup restored successfully.');
    }

    /**
     * Restore configuration settings from a backup stored on the server.
     */
    public function restoreConfigFromLocal(Request $request)
    {
        $data = $request->validate([
            'path' => 'required|string',
        ]);

        if (!Storage::exists($data['path'])) {
            return redirect()
                ->route('notices.settings.maintenance.config-backup')
                ->with('error', 'Selected backup file could not be found on the server.')
            ;
        }

        $json = Storage::get($data['path']);

        return $this->restoreConfigFromJson($json, 'Configuration restored from local backup.');
    }

    /**
     * Notices data backup & restore dashboard (alias to consolidated backup view).
     */
    public function dataBackup()
    {
        return $this->backupDashboard();
    }

    /**
     * Create a Notices data backup (notifications, issues, logs, related tables).
     */
    public function createDataBackup(Request $request)
    {
        $data = $request->validate([
            'mode'        => 'required|string|in:download,email,local',
            'email'       => 'nullable|email',
            'description' => 'nullable|string|max:255',
        ]);

        $fileName     = 'notices-data-' . now()->format('Ymd_His') . '-' . Str::random(6) . '.json';
        $relativePath = 'notices/data-backups/' . $fileName;
        $this->writeBackupFile($relativePath, function (string $absolute) use ($data): void {
            $this->backups->writeData($absolute, $data['description'] ?? null);
        });

        if ('download' === $data['mode']) {
            return $this->downloadBackup($relativePath, $fileName);
        }

        if ('email' === $data['mode']) {
            $to = $data['email'] ?? config('mail.from.address');

            if (!$to) {
                return redirect()
                    ->route('notices.settings.maintenance.data-backup')
                    ->with('error', 'No email recipient configured. Please provide an email address.')
                ;
            }

            Mail::raw('Attached is a Notices data backup generated from the Maintenance tools.', function ($message) use ($to, $relativePath, $fileName): void {
                $message->to($to)
                    ->subject('Notices data backup')
                    ->attach(Storage::path($relativePath), [
                        'as'   => $fileName,
                        'mime' => 'application/json',
                    ])
                ;
            });

            return redirect()
                ->route('notices.settings.maintenance.data-backup')
                ->with('success', 'Notices data backup generated and emailed successfully. Activity logs were not included.')
            ;
        }

        return redirect()
            ->route('notices.settings.maintenance.data-backup')
            ->with('success', 'Notices data backup saved to server at ' . $relativePath . '. Activity logs were not included.')
        ;
    }

    /**
     * Restore Notices data from an uploaded backup file.
     */
    public function restoreDataFromUpload(Request $request)
    {
        $data = $request->validate([
            'backup_file' => 'required|file|mimetypes:application/json,text/plain',
        ]);

        return $this->restoreDataFile($data['backup_file']->getRealPath(), 'Uploaded Notices data backup restored successfully.');
    }

    /**
     * Restore Notices data from a backup stored on the server.
     */
    public function restoreDataFromLocal(Request $request)
    {
        $data = $request->validate([
            'path' => 'required|string',
        ]);

        if (!Storage::exists($data['path'])) {
            return redirect()
                ->route('notices.settings.maintenance.data-backup')
                ->with('error', 'Selected backup file could not be found on the server.')
            ;
        }

        return $this->restoreDataFile(Storage::path($data['path']), 'Notices data restored from local backup.');
    }

    /**
     * Restore configuration + data from uploaded full-backup file.
     */
    public function restoreCombinedFromUpload(Request $request)
    {
        $data = $request->validate([
            'backup_file' => 'required|file|mimetypes:application/json,text/plain',
        ]);

        return $this->restoreCombinedFile($data['backup_file']->getRealPath(), 'Full backup restored (configuration and Notices data).');
    }

    /**
     * Restore configuration + data from full-backup file stored on the server.
     */
    public function restoreCombinedFromLocal(Request $request)
    {
        $data = $request->validate([
            'path' => 'required|string',
        ]);

        if (!Storage::exists($data['path'])) {
            return redirect()
                ->route('notices.settings.maintenance.config-backup')
                ->with('error', 'Selected full-backup file could not be found on the server.')
            ;
        }

        return $this->restoreCombinedFile(Storage::path($data['path']), 'Full backup restored (configuration and Notices data).');
    }

    /**
     * Unified backup endpoint; uses scope to determine what to back up.
     *
     * scope = config | data | both
     */
    public function createBackup(Request $request)
    {
        $data = $request->validate([
            'scope'       => 'required|string|in:config,data,both',
            'mode'        => 'required|string|in:download,email,local',
            'email'       => 'nullable|email',
            'description' => 'nullable|string|max:255',
        ]);

        // Delegate to existing methods so behavior stays consistent.
        switch ($data['scope']) {
            case 'config':
                return $this->createConfigBackup($request);

            case 'data':
                return $this->createDataBackup($request);

            case 'both':
            default:
                return $this->createCombinedBackup($request);
        }
    }

    /**
     * Create a combined backup (configuration + Notices data) in one file.
     */
    public function createCombinedBackup(Request $request)
    {
        $data = $request->validate([
            'mode'        => 'required|string|in:download,email,local',
            'email'       => 'nullable|email',
            'description' => 'nullable|string|max:255',
        ]);

        $configPayload = $this->buildConfigBackupPayload($data['description'] ?? null);
        $fileName      = 'notices-combined-' . now()->format('Ymd_His') . '-' . Str::random(6) . '.json';
        $relativePath  = 'notices/combined-backups/' . $fileName;
        $this->writeBackupFile($relativePath, function (string $absolute) use ($configPayload, $data): void {
            $this->backups->writeCombined($absolute, $configPayload, $data['description'] ?? null);
        });

        if ('download' === $data['mode']) {
            return $this->downloadBackup($relativePath, $fileName);
        }

        if ('email' === $data['mode']) {
            $to = $data['email'] ?? config('mail.from.address');

            if (!$to) {
                return redirect()
                    ->route('notices.settings.maintenance.config-backup')
                    ->with('error', 'No email recipient configured. Please provide an email address.')
                ;
            }

            Mail::raw('Attached is a combined Notices configuration + data backup generated from the Maintenance tools.', function ($message) use ($to, $relativePath, $fileName): void {
                $message->to($to)
                    ->subject('Notices combined backup')
                    ->attach(Storage::path($relativePath), [
                        'as'   => $fileName,
                        'mime' => 'application/json',
                    ])
                ;
            });

            return redirect()
                ->route('notices.settings.maintenance.config-backup')
                ->with('success', 'Combined configuration + data backup generated and emailed successfully. Activity logs were not included.')
            ;
        }

        return redirect()
            ->route('notices.settings.maintenance.config-backup')
            ->with('success', 'Combined configuration + data backup saved to server at ' . $relativePath . '. Activity logs were not included.')
        ;
    }

    /**
     * @return array{days: int, keep: bool}
     */
    protected function storeActivityLogRetention(Request $request, ActivityLogRetentionSettings $settings): array
    {
        $data = $request->validate([
            'activity_log_days' => 'required|integer|min:1|max:3650',
            'keep_unresolved'   => 'nullable|boolean',
        ]);

        $days = (int) $data['activity_log_days'];
        $keep = $request->boolean('keep_unresolved');
        $settings->save($days, $keep);

        return [
            'days' => $days,
            'keep' => $keep,
        ];
    }

    /**
     * Write a backup to the local disk and return once the file is closed.
     */
    protected function writeBackupFile(string $relativePath, callable $write): void
    {
        Storage::makeDirectory(dirname($relativePath));
        $write(Storage::path($relativePath));
    }

    /**
     * Stream a backup file to the browser, then delete the server copy.
     */
    protected function downloadBackup(string $relativePath, string $fileName)
    {
        $absolute = Storage::path($relativePath);

        return response()->streamDownload(function () use ($absolute, $relativePath): void {
            $input = fopen($absolute, 'rb');
            if (false !== $input) {
                fpassthru($input);
                fclose($input);
            }
            Storage::delete($relativePath);
        }, $fileName, [
            'Content-Type' => 'application/x-ndjson',
        ]);
    }

    /**
     * Restore a data backup, using the streamed format when the file is version 2.
     */
    protected function restoreDataFile(string $absolutePath, string $successMessage)
    {
        try {
            $header = $this->backups->readHeader($absolutePath);
            if (is_array($header) && ($header['type'] ?? null) === 'notices-data-backup') {
                $this->backups->restoreDataTables($absolutePath, $header);
            } else {
                $json = file_get_contents($absolutePath);
                if (false === $json) {
                    throw new \InvalidArgumentException('The selected backup file could not be read.');
                }

                return $this->restoreDataFromJson($json, $successMessage);
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('notices.settings.maintenance.data-backup')
                ->with('error', $e->getMessage())
            ;
        }

        return redirect()
            ->route('notices.settings.maintenance.data-backup')
            ->with('success', $successMessage)
        ;
    }

    /**
     * Restore a combined backup, using the streamed format when the file is version 2.
     */
    protected function restoreCombinedFile(string $absolutePath, string $successMessage)
    {
        try {
            $header = $this->backups->readHeader($absolutePath);
            if (is_array($header) && ($header['type'] ?? null) === 'notices-combined-backup') {
                if (isset($header['config']) && is_array($header['config'])) {
                    $this->applyConfigBackupPayload($header['config']);
                }
                $this->backups->restoreDataTables($absolutePath, $header);
            } else {
                $json = file_get_contents($absolutePath);
                if (false === $json) {
                    throw new \InvalidArgumentException('The selected backup file could not be read.');
                }

                return $this->restoreCombinedFromJson($json, $successMessage);
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('notices.settings.maintenance.config-backup')
                ->with('error', $e->getMessage())
            ;
        }

        return redirect()
            ->route('notices.settings.maintenance.config-backup')
            ->with('success', $successMessage)
        ;
    }

    /**
     * Build the configuration backup payload as an array.
     */
    protected function buildConfigBackupPayload(?string $description = null): array
    {
        return [
            'type'         => 'notices-configuration-backup',
            'version'      => 1,
            'generated_at' => now()->toIso8601String(),
            'description'  => $description,
            'data'         => [
                'lookup_table_overrides' => LookupTableOverride::all()->toArray(),
                'ui_messages'            => UiMessage::all()->toArray(),
                'notices_settings'       => NoticesSetting::all()->toArray(),
            ],
        ];
    }

    /**
     * Shared JSON restore logic for configuration backups.
     */
    protected function restoreConfigFromJson(string $json, string $successMessage)
    {
        $payload = json_decode($json, true);

        if (!is_array($payload) || ($payload['type'] ?? null) !== 'notices-configuration-backup') {
            return redirect()
                ->route('notices.settings.maintenance.config-backup')
                ->with('error', 'The selected file is not a valid Notices configuration backup.')
            ;
        }

        $this->applyConfigBackupPayload($payload);

        return redirect()
            ->route('notices.settings.maintenance.config-backup')
            ->with('success', $successMessage)
        ;
    }

    /**
     * Apply a configuration backup payload (array form) without redirecting.
     */
    protected function applyConfigBackupPayload(array $payload): void
    {
        $data = $payload['data'] ?? [];

        DB::transaction(function () use ($data): void {
            if (isset($data['lookup_table_overrides']) && is_array($data['lookup_table_overrides'])) {
                LookupTableOverride::truncate();
                foreach ($data['lookup_table_overrides'] as $row) {
                    // Avoid mass-assignment issues by whitelisting expected keys
                    LookupTableOverride::create([
                        'table_name'   => $row['table_name']   ?? null,
                        'record_id'    => $row['record_id']    ?? null,
                        'custom_label' => $row['custom_label'] ?? null,
                        'is_enabled'   => $row['is_enabled']   ?? true,
                        'notes'        => $row['notes']        ?? null,
                    ]);
                }
            }

            if (isset($data['ui_messages']) && is_array($data['ui_messages'])) {
                UiMessage::truncate();
                foreach ($data['ui_messages'] as $row) {
                    UiMessage::create([
                        'key'     => $row['key']     ?? null,
                        'label'   => $row['label']   ?? null,
                        'message' => $row['message'] ?? null,
                        'notes'   => $row['notes']   ?? null,
                    ]);
                }
            }

            if (isset($data['notices_settings']) && is_array($data['notices_settings'])) {
                NoticesSetting::truncate();
                foreach ($data['notices_settings'] as $row) {
                    NoticesSetting::create([
                        'key'   => $row['key']   ?? null,
                        'value' => $row['value'] ?? null,
                    ]);
                }
            }
        });
    }

    /**
     * Internal helper to render the consolidated backup dashboard view.
     */
    protected function backupDashboard()
    {
        $configFiles = collect(Storage::files('notices/config-backups'))
            ->filter(fn ($path) => str_ends_with($path, '.json'))
            ->sortDesc()
            ->values()
            ->all()
        ;

        $dataFiles = collect(Storage::files('notices/data-backups'))
            ->filter(fn ($path) => str_ends_with($path, '.json'))
            ->sortDesc()
            ->values()
            ->all()
        ;

        $combinedFiles = collect(Storage::files('notices/combined-backups'))
            ->filter(fn ($path) => str_ends_with($path, '.json'))
            ->sortDesc()
            ->values()
            ->all()
        ;

        return view('notices::settings.backup', [
            'config_backup_files'   => $configFiles,
            'data_backup_files'     => $dataFiles,
            'combined_backup_files' => $combinedFiles,
            'activity_log_days'     => app(ActivityLogRetentionSettings::class)->days(),
        ]);
    }

    /**
     * Build the Notices data backup payload.
     */
    protected function buildDataBackupPayload(?string $description = null): array
    {
        $models = $this->getDataBackupModels();

        $tables = [];
        foreach ($models as $key => $modelClass) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = new $modelClass();
            $table = $model->getTable();

            $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();

            $tables[$key] = [
                'table' => $table,
                'rows'  => $rows,
            ];
        }

        return [
            'type'         => 'notices-data-backup',
            'version'      => 1,
            'generated_at' => now()->toIso8601String(),
            'description'  => $description,
            'tables'       => $tables,
        ];
    }

    /**
     * Shared JSON restore logic for Notices data backups.
     */
    protected function restoreDataFromJson(string $json, string $successMessage)
    {
        $payload = json_decode($json, true);

        if (!is_array($payload) || ($payload['type'] ?? null) !== 'notices-data-backup') {
            return redirect()
                ->route('notices.settings.maintenance.data-backup')
                ->with('error', 'The selected file is not a valid Notices data backup.')
            ;
        }

        $this->applyDataBackupPayload($payload);

        return redirect()
            ->route('notices.settings.maintenance.data-backup')
            ->with('success', $successMessage)
        ;
    }

    /**
     * Apply a Notices data backup payload (array form) without redirecting.
     */
    protected function applyDataBackupPayload(array $payload): void
    {
        $tables = $payload['tables'] ?? [];
        $models = $this->getDataBackupModels();

        DB::transaction(function () use ($tables, $models): void {
            foreach ($models as $key => $modelClass) {
                if (!isset($tables[$key]['table'], $tables[$key]['rows']) || !is_array($tables[$key]['rows'])) {
                    continue;
                }

                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = new $modelClass();
                $table = $model->getTable();

                DB::table($table)->truncate();

                if (!empty($tables[$key]['rows'])) {
                    DB::table($table)->insert($tables[$key]['rows']);
                }
            }
        });
    }

    /**
     * Restore configuration and Notices data from a combined backup payload.
     */
    protected function restoreCombinedFromJson(string $json, string $successMessage)
    {
        $payload = json_decode($json, true);

        if (!is_array($payload) || ($payload['type'] ?? null) !== 'notices-combined-backup') {
            return redirect()
                ->route('notices.settings.maintenance.config-backup')
                ->with('error', 'The selected file is not a valid full backup file.')
            ;
        }

        if (isset($payload['config']) && is_array($payload['config'])) {
            $this->applyConfigBackupPayload($payload['config']);
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $this->applyDataBackupPayload($payload['data']);
        }

        return redirect()
            ->route('notices.settings.maintenance.config-backup')
            ->with('success', $successMessage)
        ;
    }

    /**
     * List of Eloquent models included in the Notices data backup.
     *
     * Keys are logical identifiers used in the backup payload. Values are model
     * class names used to determine table names and restore order.
     */
    protected function getDataBackupModels(): array
    {
        return [
            'notification_import_batches' => NotificationImportBatch::class,
            'notifications'               => Notification::class,
            'delivery_confirmations'      => DeliveryConfirmation::class,
            'failed_delivery_strings'     => FailedDeliveryString::class,
            'patron_delivery_issues'      => PatronDeliveryIssue::class,
            'ingested_emails'             => IngestedEmail::class,
            'activity_files'              => ActivityFile::class,
            'notification_activity_logs'  => NotificationActivityLog::class,
            'activity_log'                => ActivityLog::class,
            'job_runs'                    => JobRun::class,
            'user_activity_logs'          => UserActivityLog::class,
        ];
    }
}
