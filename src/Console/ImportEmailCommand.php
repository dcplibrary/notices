<?php

namespace Dcplibrary\Notices\Console;

use DateTimeImmutable;
use Dcplibrary\Notices\Contracts\EmailIngestionInterface;
use Dcplibrary\Notices\Models\ActivityLog;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Illuminate\Console\Command;

/**
 * Import Shoutbomb failure and summary reports from email via Microsoft Graph.
 *
 * This command fetches emails from a configured mailbox folder for a given
 * date range and parses them into IngestedEmail and DeliveryConfirmation records.
 */
class ImportEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:import-email
        {--date= : Date (YYYY-MM-DD) to operate on}
        {--from= : Start date (YYYY-MM-DD)}
        {--to=   : End date (YYYY-MM-DD)}
        {--days= : Number of days back from today (inclusive)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Shoutbomb failure and summary reports from email via Microsoft Graph.';

    public function handle(EmailIngestionInterface $ingestion): int
    {
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
        );

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        // Default to today if no dates specified.
        if (!$fromDate || !$toDate) {
            $today    = new \DateTimeImmutable('today');
            $fromDate = $today;
            $toDate   = $today;
        }

        $dates = $this->expandDateRange($fromDate, $toDate);

        $label = $fromDate->format('Y-m-d') . ($fromDate == $toDate ? '' : ' to ' . $toDate->format('Y-m-d'));

        $this->info(sprintf('[notices:import-email] Importing email reports for %s', $label));

        // Create a high-level import batch log entry for this run
        $batch = NotificationImportBatch::create([
            'source'        => 'email_reports',
            'date_for'      => $fromDate->format('Y-m-d'),
            'imported_at'   => now(),
            'identifier'    => $label,
            'records_count' => 0,
        ]);

        ActivityLog::create([
            'action'       => 'import.email.run',
            'description'  => 'Email reports import run',
            'subject_type' => NotificationImportBatch::class,
            'subject_id'   => $batch->id,
            'meta'         => [
                'date_from' => $fromDate->format('Y-m-d'),
                'date_to'   => $toDate->format('Y-m-d'),
                'command'   => 'notices:import-email',
                'options'   => $this->options(),
            ],
        ]);

        foreach ($dates as $date) {
            $this->line('');
            $this->line('[notices:import-email] ' . $date->format('Y-m-d'));
            $this->line('┌────────────────────────────────────────────┬──────────┬──────────┐');
            $this->line('│ Email Subject                              │ Reports  │ Status   │');
            $this->line('├────────────────────────────────────────────┼──────────┼──────────┤');

            // Placeholder row while ingestion runs
            $this->line(sprintf(
                '│ %-42s │ %8s │ %-10s │',
                'Loading…',
                '…',
                $this->renderBar(8, 0.0),
            ));

            $this->line('├────────────────────────────────────────────┼──────────┼──────────┤');

            $before = \Dcplibrary\Notices\Models\IngestedEmail::count();

            $ingestion->ingestForDate($date);

            $after          = \Dcplibrary\Notices\Models\IngestedEmail::count();
            $importedForDay = max(0, $after - $before);

            // Show a few of the most recent ingested emails for that day
            $dayEmails = \Dcplibrary\Notices\Models\IngestedEmail::whereDate('received_at', $date->format('Y-m-d'))
                ->orderBy('id', 'desc')
                ->take(5)
                ->get()
                ->reverse()
            ;

            foreach ($dayEmails as $email) {
                $subject = $email->subject ?: '(no subject)';
                if (mb_strlen($subject) > 40) {
                    $subject = mb_substr($subject, 0, 37) . '…';
                }

                $parsedCount = $email->parsed_record_count ?? 0;

                $this->line(sprintf(
                    '│ %-42s │ %8d │ %-10s │',
                    $subject,
                    $parsedCount,
                    $this->renderBar(8, 1.0),
                ));
            }

            $this->line('└────────────────────────────────────────────┴──────────┴──────────┘');

            ActivityLog::create([
                'action'       => 'import.email.day',
                'description'  => 'Imported email reports for ' . $date->format('Y-m-d'),
                'subject_type' => NotificationImportBatch::class,
                'subject_id'   => $batch->id,
                'meta'         => [
                    'date'             => $date->format('Y-m-d'),
                    'imported_records' => $importedForDay,
                ],
            ]);

            $this->info('✔  Completed email import for ' . $date->format('Y-m-d') . '. (' . $importedForDay . ' reports)');
        }

        $this->line('');
        $this->info('[notices:import-email] Done.');

        return self::SUCCESS;
    }

    /**
     * Resolve a date range based on the given options.
     *
     * Returns [fromDate|null, toDate|null, warnings[]].
     */
    protected function resolveDateRange(?string $date, ?string $from, ?string $to, ?string $days): array
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
}
