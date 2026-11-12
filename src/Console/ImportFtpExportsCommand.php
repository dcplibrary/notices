<?php

namespace Dcplibrary\Notices\Console;

use DateTimeImmutable;
use Dcplibrary\Notices\Contracts\NotificationImporterInterface;
use Dcplibrary\Notices\Models\ActivityLog;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Dcplibrary\Notices\Services\Import\FtpImportService;
use Illuminate\Console\Command;

/**
 * Import or list Polaris/Shoutbomb exports from FTP.
 *
 * This command focuses on FTP-accessible holds export files
 * (e.g. holds_YYYY-MM-DD_HH-MM-SS.txt) and drives the
 * NotificationImporterInterface implementation used by the
 * package. It supports both:
 *
 * - an import mode that runs per-day imports over a resolved
 *   date range; and
 * - a read-only --list mode that surfaces matching FTP files
 *   without writing to the database, suitable for admin UI buttons.
 */
class ImportFtpExportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:import-ftp
        {--date= : Date (YYYY-MM-DD) to operate on}
        {--from= : Start date (YYYY-MM-DD)}
        {--to=   : End date (YYYY-MM-DD)}
        {--days= : Number of days back from today (inclusive)}
        {--list  : List matching export files without importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import or list Polaris/Shoutbomb exports from FTP (holds and related files).';

    public function handle(NotificationImporterInterface $importer, ?FtpImportService $ftp = null): int
    {
        $isList = (bool) $this->option('list');

        $dateOpt = $this->option('date');
        $fromOpt = $this->option('from');
        $toOpt   = $this->option('to');
        $daysOpt = $this->option('days');

        // Resolve date range according to shared rules.
        [$fromDate, $toDate, $warnings] = $this->resolveDateRange(
            $dateOpt,
            $fromOpt,
            $toOpt,
            $daysOpt,
            $isList,
        );

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        if ($isList) {
            return $this->handleListMode($ftp, $fromDate, $toDate);
        }

        return $this->handleImportMode($importer, $fromDate, $toDate);
    }

    /**
     * List matching export files without importing.
     */
    protected function handleListMode(?FtpImportService $ftp, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): int
    {
        if (!config('notices.polaris.use_ftp')) {
            $this->info('[notices:import-ftp] FTP is disabled in configuration (notices.polaris.use_ftp=false).');

            return self::SUCCESS;
        }

        if (!$ftp) {
            $this->error('[notices:import-ftp] FTP service is not available.');

            return self::FAILURE;
        }

        $pattern = (string) config('notices.polaris.file_patterns.holds', 'holds_*_*.txt');

        $this->info('[notices:import-ftp] Listing FTP exports' . ($from && $to ? sprintf(' between %s and %s', $from->format('Y-m-d'), $to->format('Y-m-d')) : '') . ' (pattern: ' . $pattern . ')');

        $files = $ftp->listAvailableFiles($pattern);

        if ($from && $to) {
            $validDates  = $this->expandDateRange($from, $to);
            $dateStrings = array_map(static fn (\DateTimeImmutable $d) => $d->format('Y-m-d'), $validDates);

            $files = array_values(array_filter($files, static function (array $file) use ($dateStrings): bool {
                foreach ($dateStrings as $dateStr) {
                    if (str_contains($file['name'], $dateStr)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        if (empty($files)) {
            $this->line('No matching export files found.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line(sprintf('%-40s %-12s %-20s', 'name', 'size', 'last modified'));
        $this->line(str_repeat('-', 40 + 1 + 12 + 1 + 20));

        foreach ($files as $file) {
            $name     = $file['name']      ?? '';
            $size     = $file['size']      ?? 0;
            $time     = $file['timestamp'] ?? null;
            $sizeText = $this->formatBytes($size);
            $timeText = $time ? date('Y-m-d H:i:s', (int) $time) : 'n/a';

            $this->line(sprintf('%-40s %-12s %-20s', $name, $sizeText, $timeText));
        }

        return self::SUCCESS;
    }

    /**
     * Import exports from FTP (or bundled docs fallback) for a date range.
     */
    protected function handleImportMode(NotificationImporterInterface $importer, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): int
    {
        // Default to today if no dates specified.
        if (!$from || !$to) {
            $today = new \DateTimeImmutable('today');
            $from  = $today;
            $to    = $today;
        }

        $dates = $this->expandDateRange($from, $to);

        $label = $from->format('Y-m-d') . ($from == $to ? '' : ' to ' . $to->format('Y-m-d'));

        $this->info(sprintf('[notices:import-ftp] Importing FTP exports for %s', $label));

        foreach ($dates as $date) {
            $this->line('');
            $this->line('[notices:import-ftp] ' . $date->format('Y-m-d'));
            $this->line('┌────────────────────────────────────────────┬──────────┬──────────┐');
            $this->line('│ Source                                   │ Records  │ Status   │');
            $this->line('├────────────────────────────────────────────┼──────────┼──────────┤');

            // Initial placeholder rows (will be "animated" to full bars after import completes)
            $sources = [
                'Holds (FTP)'      => 'ftp_holds',
                'Renewals (FTP)'   => 'ftp_renew',
                'Overdues (FTP)'   => 'ftp_overdue',
                'Notification Log' => 'notification_log',
            ];

            foreach ($sources as $labelSource => $code) {
                $this->line(sprintf(
                    '│ %-42s │ %8s │ %-10s │',
                    $labelSource,
                    '…',
                    $this->renderBar(8, 0.0),
                ));
            }

            $this->line('├────────────────────────────────────────────┼──────────┼──────────┤');

            // Create separate batch records for each source type
            // This allows dashboard to track holds, renewals, and overdues independently
            $batches = [
                'ftp_holds' => NotificationImportBatch::create([
                    'source'        => 'ftp_holds',
                    'date_for'      => $date->format('Y-m-d'),
                    'imported_at'   => now(),
                    'identifier'    => 'holds_' . $date->format('Y-m-d'),
                    'records_count' => 0,
                ]),
                'ftp_renew' => NotificationImportBatch::create([
                    'source'        => 'ftp_renew',
                    'date_for'      => $date->format('Y-m-d'),
                    'imported_at'   => now(),
                    'identifier'    => 'renew_' . $date->format('Y-m-d'),
                    'records_count' => 0,
                ]),
                'ftp_overdue' => NotificationImportBatch::create([
                    'source'        => 'ftp_overdue',
                    'date_for'      => $date->format('Y-m-d'),
                    'imported_at'   => now(),
                    'identifier'    => 'overdue_' . $date->format('Y-m-d'),
                    'records_count' => 0,
                ]),
            ];

            $before = \Dcplibrary\Notices\Models\PolarisHoldNotification::count();

            // Import holds/renew/overdue files
            $importer->importShoutbombExports($date);

            // Import NotificationLog (delivery confirmations from Polaris)
            $importer->importNotificationLog($date);

            $after          = \Dcplibrary\Notices\Models\PolarisHoldNotification::count();
            $importedForDay = max(0, $after - $before);

            // Update all batch records with the total count
            // (We can't separate them per type without modifying importShoutbombExports)
            foreach ($batches as $source => $batch) {
                $batch->update(['records_count' => $importedForDay]);

                ActivityLog::create([
                    'action'       => 'import.ftp.day',
                    'description'  => 'Imported FTP exports for ' . $date->format('Y-m-d'),
                    'subject_type' => NotificationImportBatch::class,
                    'subject_id'   => $batch->id,
                    'meta'         => [
                        'source'           => $source,
                        'date'             => $date->format('Y-m-d'),
                        'imported_records' => $importedForDay,
                    ],
                ]);
            }

            // Final table state with full bars
            foreach ($sources as $labelSource => $code) {
                $this->line(sprintf(
                    '│ %-42s │ %8d │ %-10s │',
                    $labelSource,
                    $importedForDay,
                    $this->renderBar(8, 1.0),
                ));
            }

            $this->line('└────────────────────────────────────────────┴──────────┴──────────┘');

            $this->info('✔  Completed FTP import for ' . $date->format('Y-m-d') . '. (' . $importedForDay . ' rows)');
        }

        $this->line('');
        $this->info('[notices:import-ftp] Done.');

        return self::SUCCESS;
    }

    /**
     * Resolve a date range based on the given options.
     *
     * Returns [fromDate|null, toDate|null, warnings[]].
     */
    protected function resolveDateRange(?string $date, ?string $from, ?string $to, ?string $days, bool $isList): array
    {
        $warnings = [];

        $fromDate = null;
        $toDate   = null;

        // Highest precedence: explicit --date
        if ($date) {
            try {
                $fromDate = new \DateTimeImmutable($date);
                $toDate   = $fromDate;

                if ($from || $to || $days) {
                    $warnings[] = 'Ignoring --from/--to/--days because --date was provided.';
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Invalid --date value: ' . $date;
            }

            return [$fromDate, $toDate, $warnings];
        }

        // Next: explicit from/to range
        if ($from || $to) {
            try {
                if ($from) {
                    $fromDate = new \DateTimeImmutable($from);
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Invalid --from value: ' . $from;
            }

            try {
                if ($to) {
                    $toDate = new \DateTimeImmutable($to);
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Invalid --to value: ' . $to;
            }

            // If only from is provided, default to today
            if ($fromDate && !$toDate) {
                $toDate = new \DateTimeImmutable('today');
            }

            // If only to is provided, treat as single-day range
            if (!$fromDate && $toDate) {
                $fromDate = $toDate;
            }

            if ($days) {
                $warnings[] = 'Ignoring --days because --from/--to were provided.';
            }

            return [$fromDate, $toDate, $warnings];
        }

        // Next: relative range via --days
        if (null !== $days && '' !== $days) {
            $n = (int) $days;
            if ($n <= 0) {
                $warnings[] = 'Ignoring --days because it must be a positive integer.';

                return [$fromDate, $toDate, $warnings];
            }

            $toDate   = new \DateTimeImmutable('today');
            $fromDate = $toDate->sub(new \DateInterval('P' . ($n - 1) . 'D'));

            return [$fromDate, $toDate, $warnings];
        }

        // If we're listing and no dates are provided, return nulls (list all matching files).
        if ($isList) {
            return [null, null, $warnings];
        }

        // In import mode, if nothing specified, we will default to today in handleImportMode.
        return [null, null, $warnings];
    }

    /**
     * Expand a [from, to] range (inclusive) into an array of DateTimeImmutable days.
     */
    protected function expandDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $dates   = [];
        $current = $from;

        while ($current <= $to) {
            $dates[] = $current;
            $current = $current->add(new \DateInterval('P1D'));
        }

        return $dates;
    }

    /**
     * Render a simple ASCII progress bar for console output.
     */
    protected function renderBar(int $width = 10, float $ratio = 1.0): string
    {
        $ratio  = max(0.0, min(1.0, $ratio));
        $filled = (int) round($width * $ratio);

        return str_repeat('█', $filled) . str_repeat(' ', $width - $filled);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        $value = $bytes / 1024;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            ++$i;
        }

        return sprintf('%.1f %s', $value, $units[$i]);
    }
}
