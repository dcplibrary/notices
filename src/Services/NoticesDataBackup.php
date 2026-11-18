<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\ActivityFile;
use Dcplibrary\Notices\Models\ActivityLog;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Models\JobRun;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Models\UserActivityLog;
use Illuminate\Support\Facades\DB;

/**
 * Writes and restores Notices data backups without loading a whole table into memory.
 *
 * notification_activity_logs is omitted. That table is an audit trail and is
 * archived by {@see ActivityLogArchiver}. A staff backup that included every
 * row exhausted PHP's memory limit.
 */
class NoticesDataBackup
{
    public const VERSION = 2;

    /**
     * Tables written into a data backup, in restore order.
     *
     * @return array<string, class-string>
     */
    public function models(): array
    {
        return [
            'notification_import_batches' => NotificationImportBatch::class,
            'notifications'               => Notification::class,
            'delivery_confirmations'      => DeliveryConfirmation::class,
            'failed_delivery_strings'     => FailedDeliveryString::class,
            'patron_delivery_issues'      => PatronDeliveryIssue::class,
            'ingested_emails'             => IngestedEmail::class,
            'activity_files'              => ActivityFile::class,
            'activity_log'                => ActivityLog::class,
            'job_runs'                    => JobRun::class,
            'user_activity_logs'          => UserActivityLog::class,
        ];
    }

    /**
     * Why a table that used to be in the backup is no longer included.
     *
     * @return array<string, string>
     */
    public function omittedTables(): array
    {
        $days = app(ActivityLogRetentionSettings::class)->days();

        return [
            'notification_activity_logs' => 'Per-notification activity logs are kept for ' . $days . ' days and archived by notices:archive-activity-logs. They are not included in this backup.',
        ];
    }

    /**
     * Write a version-2 data backup as one JSON object per line.
     */
    public function writeData(string $absolutePath, ?string $description = null): void
    {
        $this->write($absolutePath, [
            'type'           => 'notices-data-backup',
            'version'        => self::VERSION,
            'generated_at'   => now()->toIso8601String(),
            'description'    => $description,
            'omitted_tables' => $this->omittedTables(),
            'tables'         => array_keys($this->models()),
        ]);
    }

    /**
     * Write a version-2 combined backup. Configuration is stored on the first line.
     *
     * @param array<string, mixed> $configPayload
     */
    public function writeCombined(string $absolutePath, array $configPayload, ?string $description = null): void
    {
        $this->write($absolutePath, [
            'type'           => 'notices-combined-backup',
            'version'        => self::VERSION,
            'generated_at'   => now()->toIso8601String(),
            'description'    => $description,
            'omitted_tables' => $this->omittedTables(),
            'tables'         => array_keys($this->models()),
            'config'         => $configPayload,
        ]);
    }

    /**
     * Read the first line when it is a version-2 header. Returns null for older pretty-printed JSON.
     *
     * @return null|array<string, mixed>
     */
    public function readHeader(string $absolutePath): ?array
    {
        $handle = fopen($absolutePath, 'rb');
        if (false === $handle) {
            throw new \InvalidArgumentException('The selected backup file could not be read.');
        }

        $line = fgets($handle);
        fclose($handle);

        if (!is_string($line)) {
            return null;
        }

        $decoded = json_decode($line, true);
        if (!is_array($decoded) || !isset($decoded['type'], $decoded['version'])) {
            return null;
        }

        return (int) $decoded['version'] >= self::VERSION ? $decoded : null;
    }

    /**
     * Replace the backed-up tables with the rows in a version-2 file.
     *
     * Tables that are not listed, including notification_activity_logs, are left as they are.
     *
     * @param array<string, mixed> $header
     */
    public function restoreDataTables(string $absolutePath, array $header): void
    {
        $keys   = array_values(array_intersect(array_keys($this->models()), $header['tables'] ?? []));
        $models = $this->models();

        foreach ($keys as $key) {
            $model = new $models[$key]();
            DB::table($model->getTable())->truncate();
        }

        $handle = fopen($absolutePath, 'rb');
        if (false === $handle) {
            throw new \InvalidArgumentException('The selected backup file could not be read.');
        }

        // The first line is the header written by write().
        fgets($handle);

        $batches = [];
        $tables  = [];
        foreach ($keys as $key) {
            $model         = new $models[$key]();
            $tables[$key]  = $model->getTable();
            $batches[$key] = [];
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ('' === $line) {
                    continue;
                }

                $decoded = json_decode($line, true);
                if (!is_array($decoded) || !isset($decoded['table'], $decoded['row']) || !is_array($decoded['row'])) {
                    continue;
                }

                $key = $decoded['table'];
                if (!isset($batches[$key])) {
                    continue;
                }

                $batches[$key][] = $decoded['row'];
                if (count($batches[$key]) >= 500) {
                    DB::table($tables[$key])->insert($batches[$key]);
                    $batches[$key] = [];
                }
            }
        } finally {
            fclose($handle);
        }

        foreach ($batches as $key => $rows) {
            if ([] !== $rows) {
                DB::table($tables[$key])->insert($rows);
            }
        }
    }

    /**
     * @param array<string, mixed> $header
     */
    protected function write(string $absolutePath, array $header): void
    {
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create the backup directory.');
        }

        $handle = fopen($absolutePath, 'wb');
        if (false === $handle) {
            throw new \RuntimeException('Could not open the backup file for writing.');
        }

        try {
            fwrite($handle, json_encode($header, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");

            foreach ($this->models() as $key => $modelClass) {
                $model = new $modelClass();
                $table = $model->getTable();

                DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($handle, $key): void {
                    foreach ($rows as $row) {
                        fwrite($handle, json_encode([
                            'table' => $key,
                            'row'   => (array) $row,
                        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
                    }
                });
            }
        } finally {
            fclose($handle);
        }
    }
}
