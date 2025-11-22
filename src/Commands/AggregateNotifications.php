<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Services\NotificationAggregatorService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AggregateNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notices:aggregate
                            {--date= : Specific date to aggregate (Y-m-d format)}
                            {--start-date= : Start date for range aggregation (Y-m-d format)}
                            {--end-date= : End date for range aggregation (Y-m-d format)}
                            {--yesterday : Aggregate yesterday\'s data (default)}
                            {--all : Re-aggregate all historical data}';

    /**
     * The console command description.
     */
    protected $description = 'Aggregate notification data into daily summary table';

    /**
     * Execute the console command.
     */
    public function handle(NotificationAggregatorService $aggregator): int
    {
        $this->info('🚀 Starting notification aggregation...');
        $this->newLine();

        try {
            $result = null;

            if ($this->option('all')) {
                // Re-aggregate all historical data
                $this->warn('⚠️  Re-aggregating all historical data. This may take a while...');

                if ($this->confirm('This will overwrite existing aggregated data. Are you sure?', true)) {
                    $result = $this->aggregateAllWithProgress($aggregator);
                } else {
                    $this->info('Aggregation cancelled.');
                    return Command::SUCCESS;
                }

            } elseif ($this->option('date')) {
                // Aggregate specific date
                $date = Carbon::parse($this->option('date'));
                $this->info("Aggregating notifications for {$date->format('Y-m-d')}...");

                $result = $aggregator->aggregateDate($date);

            } elseif ($this->option('start-date') && $this->option('end-date')) {
                // Aggregate date range
                $startDate = Carbon::parse($this->option('start-date'));
                $endDate = Carbon::parse($this->option('end-date'));

                $this->info("Aggregating notifications from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}...");

                $result = $this->aggregateDateRangeWithProgress($aggregator, $startDate, $endDate);

            } else {
                // Default: aggregate yesterday
                $this->info("Aggregating yesterday's notifications...");

                $result = $aggregator->aggregateYesterday();
            }

            // Display results
            $this->newLine();
            $this->line('─────────────────────────────────────────');
            $this->info('✅ Aggregation completed successfully!');
            $this->line('─────────────────────────────────────────');

            if (isset($result['date'])) {
                // Single date aggregation
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Date', $result['date']],
                        ['Combinations aggregated', $result['combinations_aggregated']],
                    ]
                );
            } else {
                // Date range aggregation
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Start date', $result['start_date']],
                        ['End date', $result['end_date']],
                        ['Total combinations', $result['combinations_aggregated']],
                    ]
                );
            }

            $this->line('─────────────────────────────────────────');
            $this->newLine();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Aggregation failed: ' . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Aggregate all historical data with progress feedback.
     */
    protected function aggregateAllWithProgress(NotificationAggregatorService $aggregator): array
    {
        // Get date range from notification_logs
        $firstDate = \Dcplibrary\Notices\Models\NotificationLog::min('notification_date');
        $lastDate = \Dcplibrary\Notices\Models\NotificationLog::max('notification_date');

        if (!$firstDate || !$lastDate) {
            $this->warn('No notification data found to aggregate');
            return [
                'success' => false,
                'message' => 'No notification data found',
                'start_date' => null,
                'end_date' => null,
                'combinations_aggregated' => 0,
            ];
        }

        $startDate = Carbon::parse($firstDate)->startOfDay();
        $endDate = Carbon::parse($lastDate)->startOfDay();

        $this->info("Found data from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        $this->newLine();

        return $this->aggregateDateRangeWithProgress($aggregator, $startDate, $endDate);
    }

    /**
     * Aggregate date range with progress bar.
     */
    protected function aggregateDateRangeWithProgress(NotificationAggregatorService $aggregator, Carbon $startDate, Carbon $endDate): array
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalAggregated = 0;
        $currentDate = $startDate->copy();

        $this->info("Processing {$totalDays} days...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        while ($currentDate->lte($endDate)) {
            $progressBar->setMessage("Processing {$currentDate->format('Y-m-d')}");

            $result = $aggregator->aggregateDate($currentDate);
            $totalAggregated += $result['combinations_aggregated'];

            $progressBar->advance();
            $currentDate->addDay();
        }

        $progressBar->setMessage('Complete!');
        $progressBar->finish();
        $this->newLine(2);

        return [
            'success' => true,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'combinations_aggregated' => $totalAggregated,
        ];
    }
}
