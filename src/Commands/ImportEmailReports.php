<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Services\EmailReportService;
use Dcplibrary\Notices\Services\ShoutbombEmailParser;
use Illuminate\Console\Command;

class ImportEmailReports extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notices:import-email-reports
                            {--mark-read : Mark imported emails as read}
                            {--move-to= : Move imported emails to specified folder}
                            {--limit=50 : Maximum number of emails to process per run}';

    /**
     * The console command description.
     */
    protected $description = 'Import Shoutbomb notification reports from email inbox';

    /**
     * Execute the console command.
     */
    public function handle(EmailReportService $emailService, ShoutbombEmailParser $parser): int
    {
        if (!config('notices.email_reports.enabled')) {
            $this->warn('⚠️  Email report import is disabled in configuration.');
            return Command::SUCCESS;
        }

        $this->info('📧 Starting email report import...');
        $this->newLine();

        try {
            // Connect to email server
            $this->line('→ Connecting to email server...');
            if (!$emailService->connect()) {
                $this->error('❌ Failed to connect to email server');
                return Command::FAILURE;
            }

            $this->info('✓ Connected successfully');
            $this->newLine();

            // Get email statistics
            $stats = $emailService->getStats();
            $this->line(sprintf(
                'Inbox: %d total messages, %d unread',
                $stats['total_messages'],
                $stats['unread']
            ));
            $this->newLine();

            // Fetch Shoutbomb reports
            $this->line('→ Fetching Shoutbomb report emails...');
            $reports = $emailService->fetchShoutbombReports();

            if (empty($reports)) {
                $this->info('✓ No new Shoutbomb reports found');
                $emailService->disconnect();
                return Command::SUCCESS;
            }

            $this->info(sprintf('✓ Found %d report email(s)', count($reports)));
            $this->newLine();

            // Process each report
            $limit = (int) $this->option('limit');
            $processed = 0;
            $totalStats = [
                'opted_out' => 0,
                'invalid' => 0,
                'undelivered_voice' => 0,
                'imported' => 0,
                'errors' => 0,
            ];

            foreach (array_slice($reports, 0, $limit) as $report) {
                $this->line(sprintf(
                    '→ Processing: %s (%s)',
                    $report['subject'],
                    $report['date']
                ));

                // Parse and import
                $result = $parser->parseAndImport($report);

                // Update totals
                $totalStats['opted_out'] += $result['opted_out'];
                $totalStats['invalid'] += $result['invalid'];
                $totalStats['undelivered_voice'] += $result['undelivered_voice'];
                $totalStats['imported'] += $result['imported'];
                $totalStats['errors'] += $result['errors'];

                $this->line(sprintf(
                    '  ✓ Imported %d records (%d opted-out, %d invalid, %d undelivered voice)',
                    $result['imported'],
                    $result['opted_out'],
                    $result['invalid'],
                    $result['undelivered_voice']
                ));

                // Mark as read if requested
                if ($this->option('mark-read')) {
                    $emailService->markAsRead($report['email_id']);
                }

                // Move to folder if requested
                if ($folder = $this->option('move-to')) {
                    $emailService->moveToFolder($report['email_id'], $folder);
                }

                $processed++;
            }

            $this->newLine();

            // Display summary
            $this->displaySummary($totalStats, $processed);

            // Disconnect
            $emailService->disconnect();
            $this->newLine();
            $this->info('✓ Email import completed successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error during email import: ' . $e->getMessage());
            if (isset($emailService)) {
                $emailService->disconnect();
            }
            return Command::FAILURE;
        }
    }

    /**
     * Display import summary
     */
    protected function displaySummary(array $stats, int $processed): void
    {
        $this->info('📊 Import Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Emails Processed', $processed],
                ['Total Records Imported', $stats['imported']],
                ['Opted-Out', $stats['opted_out']],
                ['Invalid Phone Numbers', $stats['invalid']],
                ['Undelivered Voice', $stats['undelivered_voice']],
                ['Errors', $stats['errors']],
            ]
        );
    }
}
