<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Models\NoticeFailureReport;
use Dcplibrary\Notices\Models\ShoutbombMonthlyStat;
use Dcplibrary\Notices\Services\ShoutbombFailureReportParser;
use Dcplibrary\Notices\Services\ShoutbombGraphApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckShoutbombReportsCommand extends Command
{
    protected $signature = 'shoutbomb:check-reports
                            {--dry-run : Display what would be processed without saving}
                            {--limit= : Maximum number of emails to process}
                            {--mark-read : Mark processed emails as read}';

    protected $description = 'Check Outlook for Shoutbomb report emails and process them';

    protected ShoutbombGraphApiService $graphApi;
    protected ShoutbombFailureReportParser $parser;

    public function __construct(ShoutbombGraphApiService $graphApi, ShoutbombFailureReportParser $parser)
    {
        parent::__construct();
        $this->graphApi = $graphApi;
        $this->parser = $parser;
    }

    public function handle(): int
    {
        $this->info('Starting Shoutbomb report check...');

        try {
            // Get filters from config (mirrors old shoutbomb-reports config)
            $filters = config('notices.integrations.shoutbomb_reports.filters', []);

            // Override with command options
            if ($limit = $this->option('limit')) {
                $filters['max_emails'] = (int) $limit;
            }

            if ($this->option('mark-read')) {
                $filters['mark_as_read'] = true;
            }

            // Fetch messages from Outlook
            $this->info("Fetching messages from Outlook...");
            $messages = $this->graphApi->getMessages($filters);

            if (empty($messages)) {
                $this->info('No matching emails found.');
                return self::SUCCESS;
            }

            $this->info("Found " . count($messages) . " message(s) to process.");
            $this->newLine();

            $processedEmails = 0;
            $processedRecords = 0;
            $skippedCount = 0;

            // Create and start progress bar
            $progressBar = $this->output->createProgressBar(count($messages));
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
            $progressBar->setMessage('Starting...');
            $progressBar->start();

            foreach ($messages as $message) {
                try {
                    $subject = $message['subject'] ?? 'unknown';
                    $progressBar->setMessage("Processing: " . substr($subject, 0, 50));

                    $result = $this->processMessage($message, $filters);

                    if ($result > 0) {
                        $processedEmails++;
                        $processedRecords += $result;
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $skippedCount++;
                    Log::error('Failed to process Shoutbomb report', [
                        'message_id' => $message['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->setMessage('Complete!');
            $progressBar->finish();
            $this->newLine();
            $this->newLine();
            $this->info("Processing complete!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Emails Processed', $processedEmails],
                    ['Records Extracted', $processedRecords],
                    ['Emails Skipped', $skippedCount],
                    ['Total Emails', count($messages)],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to check Shoutbomb reports: {$e->getMessage()}");
            Log::error('Shoutbomb report check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Process a single message (which may contain multiple report records)
     * Returns the number of records processed
     */
    protected function processMessage(array $message, ?array $filters = []): int
    {
        // Ensure $filters is an array even if null is passed
        $filters = $filters ?? [];

        // Get message body
        $bodyContent = $this->graphApi->getMessageBody($message, 'text');

        // Parse the message (returns array of report records)
        $records = $this->parser->parse($message, $bodyContent);

        if (empty($records)) {
            if (config('notices.integrations.shoutbomb_reports.storage.log_processing')) {
                Log::info('Skipped message - no records parsed', [
                    'subject' => $message['subject'] ?? 'unknown',
                ]);
            }
            return 0;
        }

        // Check if this email has already been processed
        if ($this->isEmailProcessed($message['id'])) {
            if (config('notices.integrations.shoutbomb_reports.storage.log_processing')) {
                Log::info('Skipped message - email already processed', [
                    'message_id' => $message['id'],
                ]);
            }
            return 0;
        }

        // Dry run mode - just display what would be saved
        if ($this->option('dry-run')) {
            $this->displayParsedRecords($records);
            return count($records);
        }

        // Save all records to database
        $saved = 0;
        DB::beginTransaction();
        try {
            foreach ($records as $record) {
                // Validate each record
                if (!$this->parser->validate($record)) {
                    continue;
                }

                // Check for duplicates
                if ($this->isRecordDuplicate($record)) {
                    continue;
                }

                NoticeFailureReport::create($record);
                $saved++;
            }

            // Mark as read if configured
            if ($filters['mark_as_read'] ?? false) {
                $this->graphApi->markAsRead($message['id']);
            }

            // Move to folder if configured
            if (!empty($filters['move_to_folder'])) {
                $this->graphApi->moveMessage($message['id'], $filters['move_to_folder']);
            }

            // Parse and save monthly statistics if this is a monthly report
            $this->processMonthlyStats($message, $bodyContent);

            DB::commit();

            if (config('notices.integrations.shoutbomb_reports.storage.log_processing')) {
                Log::info('Processed Shoutbomb report', [
                    'email_subject' => $message['subject'] ?? 'unknown',
                    'records_saved' => $saved,
                ]);
            }

            return $saved;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if email has already been processed
     */
    protected function isEmailProcessed(string $messageId): bool
    {
        return NoticeFailureReport::where('outlook_message_id', $messageId)->exists();
    }

    /**
     * Check if specific record already exists
     */
    protected function isRecordDuplicate(array $record): bool
    {
        return NoticeFailureReport::where('outlook_message_id', $record['outlook_message_id'])
            ->where(function ($query) use ($record) {
                $query->where('patron_phone', $record['patron_phone'])
                    ->orWhere('patron_id', $record['patron_id']);
            })
            ->exists();
    }

    /**
     * Process and save monthly statistics if this is a monthly report
     */
    protected function processMonthlyStats(array $message, string $bodyContent): void
    {
        $stats = $this->parser->parseMonthlyStats($message, $bodyContent);

        if (!$stats) {
            return; // Not a monthly report
        }

        // Check if already processed
        if (ShoutbombMonthlyStat::where('outlook_message_id', $message['id'])->exists()) {
            return;
        }

        // Save monthly stats
        try {
            ShoutbombMonthlyStat::create($stats);

            if (config('notices.integrations.shoutbomb_reports.storage.log_processing')) {
                Log::info('Saved monthly statistics', [
                    'report_month' => $stats['report_month'] ?? 'unknown',
                    'branch' => $stats['branch_name'] ?? 'unknown',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save monthly statistics', [
                'message_id' => $message['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display parsed records in dry-run mode
     */
    protected function displayParsedRecords(array $records): void
    {
        $this->info('Dry run mode - displaying parsed records:');

        foreach ($records as $record) {
            $this->line(sprintf(
                'Phone: %s | Barcode: %s | Patron ID: %s | Type: %s | Failure: %s',
                $record['patron_phone'] ?? 'N/A',
                $record['patron_barcode'] ?? 'N/A',
                $record['patron_id'] ?? 'N/A',
                $record['notice_type'] ?? 'N/A',
                $record['failure_type'] ?? 'N/A',
            ));
        }
    }
}