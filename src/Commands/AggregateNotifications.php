<?php

namespace Dcplibrary\Notifications\Commands;

use Dcplibrary\Notifications\Services\NotificationAggregatorService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AggregateNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:aggregate-notifications
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
                    $result = $aggregator->reAggregateAll();
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

                $result = $aggregator->aggregateDateRange($startDate, $endDate);

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
}
