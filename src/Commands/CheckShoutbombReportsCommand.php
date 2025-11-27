<?php

namespace Dcplibrary\Notices\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\NoticeFailureReport;
use Dcplibrary\Notices\Models\ShoutbombMonthlyStat;
use Dcplibrary\Notices\Services\ShoutbombFailureReportParser;
use Dcplibrary\Notices\Services\ShoutbombGraphApiService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckShoutbombReportsCommand extends Command
{
    protected $signature = 'notices:import-email-reports
                            {--dry-run : Display what would be processed without saving}
                            {--limit= : Maximum number of emails to process}
                            {--mark-read : Mark processed emails as read}
                            {--move-to= : Move processed emails to specified folder}
                            {--date= : Process emails received on a specific date (Y-m-d)}
                            {--start= : Start date for email received range (Y-m-d)}
                            {--end= : End date for email received range (Y-m-d)}
                            {--days=1 : Number of days back to include (default: 1)}
                            {--all : Process all matching emails regardless of date}';

    protected $description = 'Import Shoutbomb-related notification reports from Microsoft Graph (email)';

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

        // Validate Graph configuration (injected service already uses this config)
        $graphConfig = config('notices.integrations.shoutbomb_reports.graph');
        if (empty($graphConfig['tenant_id']) || empty($graphConfig['client_id']) || empty($graphConfig['client_secret']) || empty($graphConfig['user_email'])) {
            $this->error('Microsoft Graph API not configured. Set EMAIL_TENANT_ID, EMAIL_CLIENT_ID, EMAIL_CLIENT_SECRET, EMAIL_USER (and related EMAIL_* filters) in .env');

            return self::FAILURE;
        }

        try {
            $filters = config('notices.integrations.shoutbomb_reports.filters') ?? [];

            // Date range handling (unless --all is specified)
            if (!$this->option('all')) {
                [$startDate, $endDate] = $this->determineDateRange();
                if ($startDate && $endDate) {
                    $filters['received_after'] = $startDate->toIso8601String();
                    $filters['received_before'] = $endDate->toIso8601String();
                }
            }

            if ($limit = $this->option('limit')) {
                $filters['max_emails'] = (int) $limit;
            }

            if ($this->option('mark-read')) {
                $filters['mark_as_read'] = true;
            }

            if ($moveTo = $this->option('move-to')) {
                $filters['move_to_folder'] = $moveTo;
            }

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
                } catch (Exception $e) {
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
        } catch (Exception $e) {
            $this->error("Failed to check Shoutbomb reports: {$e->getMessage()}");
            Log::error('Shoutbomb report check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    protected function determineDateRange(): array
    {
        // --date flag: specific single date
        if ($date = $this->option('date')) {
            $target = Carbon::parse($date);

            return [$target->copy()->startOfDay(), $target->copy()->endOfDay()];
        }

        // --start/--end flags: explicit range
        if ($this->option('start') || $this->option('end')) {
            $start = $this->option('start')
                ? Carbon::parse($this->option('start'))->startOfDay()
                : now()->subDays(30)->startOfDay();
            $end = $this->option('end')
                ? Carbon::parse($this->option('end'))->endOfDay()
                : now()->endOfDay();

            return [$start, $end];
        }

        // --days flag: relative window ending today
        $days = (int) $this->option('days');
        $end = now()->endOfDay();
        $start = now()->subDays(max($days, 1) - 1)->startOfDay();

        return [$start, $end];
    }

    protected function processMessage(array $message, ?array $filters = []): int
    {
        $filters = $filters ?? [];

        $bodyContent = $this->graphApi->getMessageBody($message, 'text');
        $records = $this->parser->parse($message, $bodyContent);

        if (empty($records)) {
            return 0;
        }

        if ($this->isEmailProcessed($message['id'])) {
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->displayParsedRecords($records);

            return count($records);
        }

        $saved = 0;
        DB::beginTransaction();
        try {
            foreach ($records as $record) {
                if (!$this->parser->validate($record)) {
                    continue;
                }

                if ($this->isRecordDuplicate($record)) {
                    continue;
                }

                NoticeFailureReport::create($record);
                $saved++;
            }

            if ($filters['mark_as_read'] ?? false) {
                $this->graphApi->markAsRead($message['id']);
            }

            if (!empty($filters['move_to_folder'])) {
                $this->graphApi->moveMessage($message['id'], $filters['move_to_folder']);
            }

            $this->processMonthlyStats($message, $bodyContent);

            DB::commit();

            Log::info('Processed Shoutbomb report', [
                'email_subject' => $message['subject'] ?? 'unknown',
                'records_saved' => $saved,
            ]);

            return $saved;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function isEmailProcessed(string $messageId): bool
    {
        return NoticeFailureReport::where('outlook_message_id', $messageId)->exists();
    }

    protected function isRecordDuplicate(array $record): bool
    {
        return NoticeFailureReport::where('outlook_message_id', $record['outlook_message_id'])
            ->where(function ($query) use ($record) {
                $query->where('patron_phone', $record['patron_phone'])
                    ->orWhere('patron_id', $record['patron_id']);
            })
            ->exists();
    }

    protected function processMonthlyStats(array $message, string $bodyContent): void
    {
        $stats = $this->parser->parseMonthlyStats($message, $bodyContent);

        if (!$stats) {
            return;
        }

        if (ShoutbombMonthlyStat::where('outlook_message_id', $message['id'])->exists()) {
            return;
        }

        try {
            ShoutbombMonthlyStat::create($stats);
            Log::info('Saved monthly statistics', [
                'report_month' => $stats['report_month'] ?? 'unknown',
                'branch' => $stats['branch_name'] ?? 'unknown',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to save monthly statistics', [
                'message_id' => $message['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function displayParsedRecords(array $records): void
    {
        $this->newLine();
        $this->info("Parsed " . count($records) . " Record(s) (Dry Run):");
        $this->newLine();

        foreach ($records as $index => $record) {
            $this->line("Record #" . ($index + 1));
            $this->table(
                ['Field', 'Value'],
                [
                    ['Subject', $record['subject'] ?? 'N/A'],
                    ['Patron Phone', $record['patron_phone'] ?? 'N/A'],
                    ['Patron ID', $record['patron_id'] ?? 'N/A'],
                    ['Patron Barcode', $record['patron_barcode'] ?? 'N/A'],
                    ['Failure Type', $record['failure_type'] ?? 'N/A'],
                    ['Received At', $record['received_at'] ?? 'N/A'],
                ]
            );
            $this->newLine();
        }
    }
}
