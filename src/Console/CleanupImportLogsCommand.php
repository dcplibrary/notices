<?php

namespace Dcplibrary\Notices\Console;

use DateTimeImmutable;
use Dcplibrary\Notices\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * Delete locally stored import/export files for a date range.
 *
 * This command operates on the directory configured via
 * `notices.import.local_storage` (defaults to storage/app/notices-exports)
 * and deletes any matching Polaris/Shoutbomb export files such as:
 * - holds_YYYY-MM-DD_*.txt
 * - renew_YYYY-MM-DD_*.txt
 * - overdue_YYYY-MM-DD_*.txt
 * - notification_log_YYYY-MM-DD_*.txt
 * - notification_history_YYYY-MM-DD_*.txt
 * - PhoneNotices_YYYY-MM-DD_*.csv
 *
 * It shares the same date range semantics as `notices:import-ftp`:
 * - --date: a single date
 * - --from/--to: inclusive date range
 * - --days: N days back from today (including today)
 *
 * Options:
 * - --all: ignore date range and delete ALL matching import files
 * - --dry-run: show which files WOULD be deleted without deleting them
 * - --force: skip interactive confirmation (for non-interactive/web-triggered runs)
 */
class CleanupImportLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notices:cleanup-import-logs
        {--date= : Date (YYYY-MM-DD) to operate on}
        {--from= : Start date (YYYY-MM-DD)}
        {--to=   : End date (YYYY-MM-DD)}
        {--days= : Number of days back from today (inclusive)}
        {--all   : Delete all import files regardless of date range}
        {--dry-run : List matching files without deleting them}
        {--force : Skip interactive confirmation prompt when deleting files}';

    /**
     * The console command description.
     */
    protected $description = 'Delete locally stored Polaris/Shoutbomb import/export files by date range.';

    public function handle(): int
    {
        $baseDir = (string) config('notices.import.local_storage', storage_path('app/notices-exports'));
        $all     = (bool) $this->option('all');
        $dryRun  = (bool) $this->option('dry-run');
        $force   = (bool) $this->option('force');

        if (!is_dir($baseDir)) {
            $this->info('[notices:cleanup-import-logs] Import storage directory does not exist: ' . $baseDir);

            return self::SUCCESS;
        }

        $this->info('[notices:cleanup-import-logs] Using import storage directory: ' . $baseDir);

        $dateOpt = $this->option('date');
        $fromOpt = $this->option('from');
        $toOpt   = $this->option('to');
        $daysOpt = $this->option('days');

        $fromDate = null;
        $toDate   = null;

        if (!$all) {
            [$fromDate, $toDate, $warnings] = $this->resolveDateRange(
                $dateOpt,
                $fromOpt,
                $toOpt,
                $daysOpt,
            );

            foreach ($warnings as $warning) {
                $this->warn($warning);
            }

            // If we could not resolve a valid date range, abort rather than risk
            // operating on an unintended set of files.
            if (null === $fromDate || null === $toDate) {
                $this->error('Could not resolve a valid date range. No files will be deleted.');

                return self::FAILURE;
            }

            $label = $fromDate->format('Y-m-d');
            if ($fromDate != $toDate) {
                $label .= ' to ' . $toDate->format('Y-m-d');
            }

            $this->info('[notices:cleanup-import-logs] Date range: ' . $label);
        }

        $patterns = (array) (config('notices.polaris.file_patterns') ?? []);

        // Ensure we also cover PhoneNotices CSVs even if not explicitly
        // present in file_patterns.
        if (!in_array('PhoneNotices*.csv', $patterns, true)) {
            $patterns[] = 'PhoneNotices*.csv';
        }

        $files = $this->collectFiles($baseDir, $patterns, $fromDate, $toDate, $all);

        if (empty($files)) {
            $this->info('No matching import files found.');

            // Log no-op cleanup attempt for traceability.
            ActivityLog::create([
                'action'       => $dryRun ? 'cleanup.import_logs.dry_run' : 'cleanup.import_logs',
                'description'  => 'No matching import files found for cleanup.',
                'subject_type' => null,
                'subject_id'   => null,
                'meta'         => [
                    'base_dir' => $baseDir,
                    'all'      => $all,
                    'dry_run'  => $dryRun,
                    'force'    => $force,
                    'from'     => $fromDate ? $fromDate->format('Y-m-d') : null,
                    'to'       => $toDate ? $toDate->format('Y-m-d') : null,
                    'files'    => [],
                ],
            ]);

            return self::SUCCESS;
        }

        $this->line('');
        $this->info(sprintf('Found %d file(s) to %s:', count($files), $dryRun ? 'inspect (dry-run)' : 'delete'));
        $this->line(sprintf('%-4s %-40s %-12s %-20s', '#', 'name', 'size', 'last modified'));
        $this->line(str_repeat('-', 4 + 1 + 40 + 1 + 12 + 1 + 20));

        $i         = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            ++$i;
            $name      = $file['name'];
            $size      = $file['size'];
            $timestamp = $file['timestamp'];

            $totalSize += $size;

            $this->line(sprintf(
                '%-4d %-40s %-12s %-20s',
                $i,
                $name,
                $this->formatBytes($size),
                $timestamp ? date('Y-m-d H:i:s', $timestamp) : 'n/a'
            ));
        }

        $this->line('');
        $this->info('Total size: ' . $this->formatBytes($totalSize));

        // Log the planned operation in the activity log before any destructive action.
        ActivityLog::create([
            'action'      => $dryRun ? 'cleanup.import_logs.dry_run' : 'cleanup.import_logs',
            'description' => $dryRun
                ? 'Planned cleanup of import logs (dry run)'
                : 'Cleanup of import logs',
            'subject_type' => null,
            'subject_id'   => null,
            'meta'         => [
                'base_dir'    => $baseDir,
                'all'         => $all,
                'dry_run'     => $dryRun,
                'force'       => $force,
                'from'        => $fromDate ? $fromDate->format('Y-m-d') : null,
                'to'          => $toDate ? $toDate->format('Y-m-d') : null,
                'file_count'  => count($files),
                'total_bytes' => $totalSize,
                'files'       => array_map(static fn (array $file) => $file['name'], $files),
            ],
        ]);

        if ($dryRun) {
            $this->info('Dry run complete. No files were deleted.');

            return self::SUCCESS;
        }

        if (!$force) {
            if (!$this->confirm('Are you sure you want to delete these files?')) {
                $this->info('Aborted. No files were deleted.');

                return self::SUCCESS;
            }
        } else {
            $this->info('Skipping confirmation because --force was provided.');
        }

        $deleted = 0;
        $failed  = 0;

        foreach ($files as $file) {
            $path = $baseDir . DIRECTORY_SEPARATOR . $file['name'];

            if (!is_file($path)) {
                continue;
            }

            if (@unlink($path)) {
                ++$deleted;
            } else {
                ++$failed;
                $this->error('Failed to delete: ' . $file['name']);
            }
        }

        $this->line('');
        $this->info(sprintf('Deleted %d file(s).', $deleted));
        if ($failed > 0) {
            $this->warn(sprintf('%d file(s) could not be deleted.', $failed));
        }

        // Log the final outcome with delete/failed counts.
        ActivityLog::create([
            'action'       => 'cleanup.import_logs.result',
            'description'  => 'Completed cleanup of import logs',
            'subject_type' => null,
            'subject_id'   => null,
            'meta'         => [
                'base_dir' => $baseDir,
                'all'      => $all,
                'force'    => $force,
                'from'     => $fromDate ? $fromDate->format('Y-m-d') : null,
                'to'       => $toDate ? $toDate->format('Y-m-d') : null,
                'deleted'  => $deleted,
                'failed'   => $failed,
            ],
        ]);

        return self::SUCCESS;
    }

    /**
     * Collect files from the base directory matching the given patterns and
     * optional date range.
     *
     * @return array<int, array{name:string,size:int,timestamp:null|int}>
     */
    protected function collectFiles(string $baseDir, array $patterns, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $all): array
    {
        $files = [];

        $dateStrings = [];
        if (!$all && $from && $to) {
            foreach ($this->expandDateRange($from, $to) as $date) {
                $dateStrings[] = $date->format('Y-m-d');
            }
        }

        // Normalize patterns to simple list of glob-style patterns (values only).
        $normalizedPatterns = [];
        foreach ($patterns as $value) {
            $normalizedPatterns[] = $value;
        }

        $baseDirReal = rtrim($baseDir, DIRECTORY_SEPARATOR);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDirReal, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $path => $info) {
            if (!$info->isFile()) {
                continue;
            }

            $name = $info->getFilename();

            // Match against any of the configured filename patterns using fnmatch.
            $matchesPattern = false;
            foreach ($normalizedPatterns as $pattern) {
                if (fnmatch($pattern, $name)) {
                    $matchesPattern = true;

                    break;
                }
            }

            if (!$matchesPattern) {
                continue;
            }

            // Apply date filtering based on filename if a date range is in effect.
            if (!$all && !empty($dateStrings)) {
                $matchesDate = false;
                foreach ($dateStrings as $ymd) {
                    if (str_contains($name, $ymd)) {
                        $matchesDate = true;

                        break;
                    }
                }

                if (!$matchesDate) {
                    continue;
                }
            }

            // Store relative path from base directory so we can reconstruct full path later.
            $relativePath = ltrim(str_replace($baseDirReal . DIRECTORY_SEPARATOR, '', $path), DIRECTORY_SEPARATOR);

            $files[] = [
                'name'      => $relativePath,
                'size'      => $info->getSize(),
                'timestamp' => $info->getMTime(),
            ];
        }

        // Sort by name for stable output
        usort($files, static function (array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        return $files;
    }

    /**
     * Resolve a date range from CLI options.
     *
     * Returns [fromDate|null, toDate|null, warnings[]].
     */
    protected function resolveDateRange(?string $date, ?string $from, ?string $to, ?string $days): array
    {
        $warnings = [];

        if ($date && ($from || $to || $days)) {
            $warnings[] = 'Ignoring --from/--to/--days because --date was provided.';
        }

        if ($date) {
            try {
                $parsed = new \DateTimeImmutable($date);

                return [$parsed, $parsed, $warnings];
            } catch (\Throwable $e) {
                $warnings[] = 'Invalid --date value: ' . $date . ' (' . $e->getMessage() . ')';

                return [null, null, $warnings];
            }
        }

        if ($from || $to) {
            if (!$from || !$to) {
                $warnings[] = 'Both --from and --to must be provided when specifying a date range.';

                return [null, null, $warnings];
            }

            try {
                $fromDate = new \DateTimeImmutable($from);
                $toDate   = new \DateTimeImmutable($to);

                if ($fromDate > $toDate) {
                    $warnings[]          = '--from date is after --to date; swapping values.';
                    [$fromDate, $toDate] = [$toDate, $fromDate];
                }

                return [$fromDate, $toDate, $warnings];
            } catch (\Throwable $e) {
                $warnings[] = 'Invalid --from/--to values: ' . $e->getMessage();

                return [null, null, $warnings];
            }
        }

        if ($days) {
            $daysInt = (int) $days;

            if ($daysInt <= 0) {
                $warnings[] = '--days must be a positive integer.';

                return [null, null, $warnings];
            }

            $today = new \DateTimeImmutable('today');
            $from  = $today->sub(new \DateInterval('P' . ($daysInt - 1) . 'D'));

            return [$from, $today, $warnings];
        }

        // Default: today only
        $today = new \DateTimeImmutable('today');

        return [$today, $today, $warnings];
    }

    /**
     * Expand a date range into an array of DateTimeImmutable instances.
     *
     * @return \DateTimeImmutable[]
     */
    protected function expandDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $dates  = [];
        $cursor = $from;

        while ($cursor <= $to) {
            $dates[] = $cursor;
            $cursor  = $cursor->add(new \DateInterval('P1D'));
        }

        return $dates;
    }

    /**
     * Format bytes as a human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            ++$i;
        }

        return sprintf('%.1f %s', $value, $units[$i]);
    }
}
