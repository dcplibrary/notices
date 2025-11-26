<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\SyncLog;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class SyncController extends Controller
{
    public function __construct()
    {
        // Admin only
        $this->middleware(function ($request, $next) {
            if (!Auth::check() || !Auth::user()->inGroup('Computer Services')) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        });
    }

    /**
     * Run all sync operations: Polaris import → Shoutbomb sync → Aggregation.
     */
    public function syncAll(Request $request): JsonResponse
    {
        // Increase execution time limit for multiple operations (5 minutes)
        set_time_limit(300);

        $log = SyncLog::create([
            'operation_type' => 'sync_all',
            'status' => 'running',
            'started_at' => now(),
            'user_id' => Auth::id(),
        ]);

        $results = [];
        $hasErrors = false;

        $rangeOptions = $this->buildRangeOptionsFromRequest($request);

        // Step 1: Import from Polaris
        try {
            $polarisResult = $this->runImportPolaris($rangeOptions);
            $results['polaris'] = $polarisResult;
            if ($polarisResult['status'] === 'error') {
                $hasErrors = true;
            }
        } catch (Exception $e) {
            $results['polaris'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $hasErrors = true;
        }

        // Step 2: Sync Shoutbomb phone notices to notification_logs
        try {
            $syncResult = $this->runSyncShoutbombToLogs($rangeOptions);
            $results['shoutbomb_sync'] = $syncResult;
            if ($syncResult['status'] === 'error') {
                $hasErrors = true;
            }
        } catch (Exception $e) {
            $results['shoutbomb_sync'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $hasErrors = true;
        }

        // Step 3: Run aggregation (continue even if imports had errors)
        try {
            $aggregateResult = $this->runAggregate($rangeOptions);
            $results['aggregate'] = $aggregateResult;
            if ($aggregateResult['status'] === 'error') {
                $hasErrors = true;
            }
        } catch (Exception $e) {
            $results['aggregate'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $hasErrors = true;
        }

        // Calculate total records processed
        $totalRecords = ($results['polaris']['records'] ?? 0) +
                       ($results['shoutbomb_sync']['records'] ?? 0);

        if ($hasErrors) {
            $log->markCompletedWithErrors($results, 'One or more operations had errors');
        } else {
            $log->markCompleted($results, $totalRecords);
        }

        return response()->json([
            'success' => !$hasErrors,
            'results' => $results,
            'log_id' => $log->id,
        ]);
    }

    /**
     * Import from Polaris only.
     */
    public function importPolaris(Request $request): JsonResponse
    {
        $log = SyncLog::create([
            'operation_type' => 'import_polaris',
            'status' => 'running',
            'started_at' => now(),
            'user_id' => Auth::id(),
        ]);

        try {
            $options = [];

            // Map JSON payload to Artisan options for notices:import-polaris
            if ($request->boolean('all')) {
                $options['--all'] = true;
            } else {
                if ($request->filled('date')) {
                    $options['--date'] = $request->input('date');
                } elseif ($request->filled('start') || $request->filled('end')) {
                    if ($request->filled('start')) {
                        $options['--start'] = $request->input('start');
                    }
                    if ($request->filled('end')) {
                        $options['--end'] = $request->input('end');
                    }
                } elseif ($request->filled('days')) {
                    $options['--days'] = (int) $request->input('days');
                }
            }

            $result = $this->runImportPolaris($options);

            if ($result['status'] === 'success') {
                $log->markCompleted(['polaris' => $result], $result['records'] ?? 0);
            } else {
                $log->markCompletedWithErrors(['polaris' => $result], $result['message'] ?? '');
            }

            return response()->json($result);
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import Shoutbomb Reports via external package command (shoutbomb:check-reports).
     */
    public function importShoutbombReports(): JsonResponse
    {
        // Increase execution time limit for email processing (5 minutes)
        set_time_limit(300);

        $log = SyncLog::create([
            'operation_type' => 'import_shoutbomb_reports',
            'status' => 'running',
            'started_at' => now(),
            'user_id' => Auth::id(),
        ]);

        try {
            $result = $this->runImportShoutbombReports();

            if ($result['status'] === 'success') {
                $log->markCompleted(['shoutbomb_reports' => $result], $result['records'] ?? 0);
            } else {
                $log->markCompletedWithErrors(['shoutbomb_reports' => $result], $result['message'] ?? '');
            }

            return response()->json($result);
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import Shoutbomb Submissions (what was sent to Shoutbomb).
     */
    public function importShoutbombSubmissions(): JsonResponse
    {
        // Increase execution time limit for large datasets (5 minutes)
        set_time_limit(300);

        $log = SyncLog::create([
            'operation_type' => 'import_shoutbomb_submissions',
            'status' => 'running',
            'started_at' => now(),
            'user_id' => Auth::id(),
        ]);

        try {
            $result = $this->runImportShoutbombSubmissions();

            if ($result['status'] === 'success') {
                $log->markCompleted(['shoutbomb_submissions' => $result], $result['records'] ?? 0);
            } else {
                $log->markCompletedWithErrors(['shoutbomb_submissions' => $result], $result['message'] ?? '');
            }

            return response()->json($result);
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import FTP files (PhoneNotices + Shoutbomb submissions + Patrons)
     * via a single blocking HTTP call (used by the non-Livewire sync page).
     */
    public function importFTPFiles(Request $request): JsonResponse
    {
        // Increase execution time limit for large imports (5 minutes)
        set_time_limit(300);

        $log = SyncLog::create([
            'operation_type' => 'import_ftp_files',
            'status' => 'running',
            'started_at' => now(),
            'user_id' => Auth::id(),
        ]);

        try {
            $result = $this->runImportFTPFiles(
                $request->input('from'),
                $request->input('to'),
                $request->boolean('import_patrons', false)
            );

            if ($result['status'] === 'success') {
                $log->markCompleted(['ftp_files' => $result], $result['records'] ?? 0);
            } else {
                $log->markCompletedWithErrors(['ftp_files' => $result], $result['message'] ?? '');
            }

            return response()->json($result);
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stream FTP files import output for the Livewire Sync & Import UI.
     *
     * Returns newline-delimited JSON (NDJSON) where each line is either:
     * - { "progress": "raw line" }
     * - { "completed": true, "success": bool, "message": string, "stats": {...} }
     */
    public function streamImportFTPFiles(Request $request)
    {
        // We intentionally do NOT wrap this in SyncLog; this endpoint is for live streaming only.
        set_time_limit(300);

        // Disable all output buffering for streaming to work properly behind proxies
        while (ob_get_level()) {
            ob_end_clean();
        }

        $command = $request->input('command');

        if (!is_array($command) || empty($command)) {
            // Fallback: default command mirrors the Livewire component default
            $command = ['php', base_path('artisan'), 'notices:import-ftp-files'];
        }

        return response()->stream(function () use ($command) {
            // Disable output buffering at the stream level too
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');

            $process = new Process($command, base_path());
            $process->setTimeout(3600);
            $process->start();

            $buffer = '';
            $stats = [];

            foreach ($process as $type => $data) {
                $buffer .= $data;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $pos), "\r\n");
                    $buffer = substr($buffer, $pos + 1);

                    if ($line === '') {
                        continue;
                    }

                    // Capture summary stats from the ASCII table at the end of the command
                    // Lines look like: "| PhoneNotices | 32 |"
                    if (preg_match('/^\|\s*([^|]+?)\s*\|\s*(\d+)\s*\|$/', $line, $m)) {
                        $label = trim($m[1]);
                        $value = (int) $m[2];
                        $stats[$label] = $value;
                    }

                    $payload = ['progress' => $line];

                    echo json_encode($payload) . "\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }

            // Final payload with completion + stats
            $exitCode = $process->getExitCode();
            $success = $exitCode === 0;

            $finalPayload = [
                'completed' => true,
                'success' => $success,
                'message' => $success
                    ? 'FTP Files Import completed successfully!'
                    : 'FTP Files Import failed',
            ];

            if (!empty($stats)) {
                $finalPayload['stats'] = $stats;
            }

            echo json_encode($finalPayload) . "\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no', // Nginx: disable buffering
            'Pragma' => 'no-cache', // HTTP/1.0 compatibility
            'Expires' => '0', // Proxies: don't cache
        ]);
    }

    /**
     * Stub endpoint for cancelling an in-progress FTP import stream.
     */
    public function cancelImportFTPFiles(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Cancellation request received.',
        ]);
    }

    /**
     * Sync Shoutbomb phone notices to notification_logs.
     */
    public function syncShoutbombToLogs(): JsonResponse
    {
        $log = SyncLog::create([
            'operation_type' => 'sync_shoutbomb_to_logs',
            'status' => 'running',
            'started_at' => now(),
            'user_id' => Auth::id(),
        ]);

        try {
            $result = $this->runSyncShoutbombToLogs();

            if ($result['status'] === 'success') {
                $log->markCompleted(['sync' => $result], $result['records'] ?? 0);
            } else {
                $log->markCompletedWithErrors(['sync' => $result], $result['message'] ?? '');
            }

            return response()->json($result);
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run aggregation.
     */
    public function aggregate(): JsonResponse
    {
        $log = SyncLog::create([
            'operation_type' => 'aggregate',
            'status' => 'running',
            'started_at' => now(),
            'user_id' => Auth::id(),
        ]);

        try {
            $result = $this->runAggregate();

            if ($result['status'] === 'success') {
                $log->markCompleted(['aggregate' => $result]);
            } else {
                $log->markCompletedWithErrors(['aggregate' => $result], $result['message'] ?? '');
            }

            return response()->json($result);
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test connections to Polaris and Shoutbomb.
     */
    public function testConnections(): JsonResponse
    {
        $results = [];

        // Test Polaris connection
        try {
            DB::connection('polaris')->getPdo();
            $results['polaris'] = [
                'status' => 'success',
                'message' => 'Connected successfully',
            ];
        } catch (Exception $e) {
            $results['polaris'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Test Shoutbomb FTP (if enabled)
        if (config('notices.shoutbomb.enabled')) {
            try {
                // Simple FTP connection test
                $ftp = ftp_connect(
                    config('notices.shoutbomb.ftp.host'),
                    config('notices.shoutbomb.ftp.port'),
                    config('notices.shoutbomb.ftp.timeout', 30)
                );

                if ($ftp && ftp_login(
                    $ftp,
                    config('notices.shoutbomb.ftp.username'),
                    config('notices.shoutbomb.ftp.password')
                )) {
                    $results['shoutbomb_ftp'] = [
                        'status' => 'success',
                        'message' => 'Connected successfully',
                    ];
                    ftp_close($ftp);
                } else {
                    $results['shoutbomb_ftp'] = [
                        'status' => 'error',
                        'message' => 'Failed to connect or login',
                    ];
                }
            } catch (Exception $e) {
                $results['shoutbomb_ftp'] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        } else {
            $results['shoutbomb_ftp'] = [
                'status' => 'disabled',
                'message' => 'Shoutbomb imports are disabled',
            ];
        }

        return response()->json($results);
    }

    /**
     * Get sync logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $logs = SyncLog::latest('started_at')
            ->limit($request->input('limit', 20))
            ->get();

        return response()->json($logs);
    }

    /**
     * Get a specific sync log with full details.
     */
    public function getLog(int $id): JsonResponse
    {
        $log = SyncLog::findOrFail($id);

        return response()->json([
            'id' => $log->id,
            'operation_type' => $log->operation_type,
            'status' => $log->status,
            'started_at' => $log->started_at->format('M d, Y g:i A'),
            'completed_at' => $log->completed_at?->format('M d, Y g:i A'),
            'duration_seconds' => $log->duration_seconds,
            'records_processed' => $log->records_processed,
            'error_message' => $log->error_message,
            'results' => $log->results,
            'user_id' => $log->user_id,
        ]);
    }

/**
 * Normalize JSON range payload into Artisan-style options.
 *
 * Accepts: all/date/start/end/days on the request.
 *
 * @return array<string,mixed>
 */
private function buildRangeOptionsFromRequest(Request $request): array
{
    $options = [];

    if ($request->boolean('all')) {
        $options['--all'] = true;

        return $options;
    }

    if ($request->filled('date')) {
        $options['--date'] = $request->input('date');
    } elseif ($request->filled('start') || $request->filled('end')) {
        if ($request->filled('start')) {
            $options['--start'] = $request->input('start');
        }
        if ($request->filled('end')) {
            $options['--end'] = $request->input('end');
        }
    } elseif ($request->filled('days')) {
        $options['--days'] = (int) $request->input('days');
    }

    return $options;
}

    /**
     * Run Polaris import command.
     */
    private function runImportPolaris(array $options = []): array
    {
        $exitCode = Artisan::call('notices:import-polaris', $options);
        $output = Artisan::output();

        // Parse output to get record count
        preg_match('/Imported (\d+) notification/', $output, $matches);
        $records = isset($matches[1]) ? (int) $matches[1] : 0;

        return [
            'status' => $exitCode === 0 ? 'success' : 'error',
            'message' => trim($output),
            'records' => $records,
        ];
    }

    /**
     * Run Shoutbomb reports check via email (Graph API).
     */
    private function runImportShoutbombReports(): array
    {
        // Preflight: ensure the command is registered so web-triggered Artisan::call works
        $commands = Artisan::all();
        if (!array_key_exists('notices:import-email-reports', $commands)) {
            return [
                'status' => 'error',
                'message' => "Command 'notices:import-email-reports' is not registered. Check that the CheckShoutbombReportsCommand is loaded.",
            ];
        }

        $exitCode = Artisan::call('notices:import-email-reports', ['--mark-read' => true]);
        $output = Artisan::output();

        // Attempt to parse a generic processed count if present
        preg_match('/Records Extracted[^\d]*(\d+)/i', $output, $matches);
        $records = isset($matches[1]) ? (int) $matches[1] : null;

        return [
            'status' => $exitCode === 0 ? 'success' : 'error',
            'message' => trim($output),
            'records' => $records,
        ];
    }

/**
 * Run sync Shoutbomb to logs command.
 */
private function runSyncShoutbombToLogs(array $rangeOptions = []): array
{
    // Only --days is currently supported by the sync command; derive from rangeOptions or default to 30
    $days = 30;
    if (isset($rangeOptions['--days'])) {
        $days = (int) $rangeOptions['--days'];
    }

    $exitCode = Artisan::call('notices:sync-shoutbomb-to-logs', [
        '--days'  => $days,
        '--force' => true,
    ]);
        $output = Artisan::output();

        // Parse output to get record count
        preg_match('/Synced.*?(\d+)/i', $output, $matches);
        $records = isset($matches[1]) ? (int) $matches[1] : 0;

        return [
            'status' => $exitCode === 0 ? 'success' : 'error',
            'message' => trim($output),
            'records' => $records,
        ];
    }

/**
 * Run aggregation command.
 */
private function runAggregate(array $rangeOptions = []): array
{
    $options = [];

    if (isset($rangeOptions['--all'])) {
        $options['--all'] = true;
    } elseif (isset($rangeOptions['--date'])) {
        $options['--date'] = $rangeOptions['--date'];
    } elseif (isset($rangeOptions['--start']) || isset($rangeOptions['--end'])) {
        if (isset($rangeOptions['--start'])) {
            $options['--start'] = $rangeOptions['--start'];
        }
        if (isset($rangeOptions['--end'])) {
            $options['--end'] = $rangeOptions['--end'];
        }
    } elseif (isset($rangeOptions['--days'])) {
        $options['--days'] = $rangeOptions['--days'];
    }

    $exitCode = Artisan::call('notices:aggregate', $options);
    $output = Artisan::output();

    return [
        'status'  => $exitCode === 0 ? 'success' : 'error',
        'message' => trim($output),
    ];
}

    /**
     * Run Shoutbomb submissions import command.
     */
    private function runImportShoutbombSubmissions(): array
    {
        $exitCode = Artisan::call('notices:import-shoutbomb-submissions', ['--all' => true]);
        $output = Artisan::output();

        // Parse output to get record counts
        $records = 0;
        if (preg_match('/Holds[^\d]*(\d+)/i', $output, $m)) {
            $records += (int) $m[1];
        }
        if (preg_match('/Overdues[^\d]*(\d+)/i', $output, $m)) {
            $records += (int) $m[1];
        }
        if (preg_match('/Renewals[^\d]*(\d+)/i', $output, $m)) {
            $records += (int) $m[1];
        }

        return [
            'status' => $exitCode === 0 ? 'success' : 'error',
            'message' => trim($output),
            'records' => $records,
        ];
    }

    /**
     * Run FTP files import command (PhoneNotices + Shoutbomb submissions + Patrons).
     */
    private function runImportFTPFiles(?string $from = null, ?string $to = null, bool $importPatrons = false): array
    {
        $options = [];

        if ($from) {
            $options['--from'] = $from;
        }
        if ($to) {
            $options['--to'] = $to;
        }
        if ($importPatrons) {
            $options['--import-patrons'] = true;
        }

        // If no dates provided, default to today
        if (empty($from) && empty($to)) {
            $options['--from'] = now()->format('Y-m-d');
            $options['--to'] = now()->format('Y-m-d');
        }

        $exitCode = Artisan::call('notices:import-ftp-files', $options);
        $output = Artisan::output();

        // Parse output to get record counts
        $records = 0;
        $patronsImported = false;

        if (preg_match('/PhoneNotices[^\d]*(\d+)/i', $output, $m)) {
            $records += (int) $m[1];
        }
        if (preg_match('/Holds[^\d]*(\d+)/i', $output, $m)) {
            $records += (int) $m[1];
        }
        if (preg_match('/Overdues[^\d]*(\d+)/i', $output, $m)) {
            $records += (int) $m[1];
        }
        if (preg_match('/Renewals[^\d]*(\d+)/i', $output, $m)) {
            $records += (int) $m[1];
        }
        if (preg_match('/Voice.*?(\d+).*?new/i', $output, $m)) {
            $patronsImported = true;
            $records += (int) $m[1];
        }
        if (preg_match('/Text.*?(\d+).*?new/i', $output, $m)) {
            $patronsImported = true;
            $records += (int) $m[1];
        }

        return [
            'status' => $exitCode === 0 ? 'success' : 'error',
            'message' => trim($output),
            'records' => $records,
            'patrons_imported' => $patronsImported,
        ];
    }
}
