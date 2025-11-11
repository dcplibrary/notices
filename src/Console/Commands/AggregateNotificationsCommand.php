<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Services\NotificationAggregatorService;
use Illuminate\Console\Command;

class AggregateNotificationsCommand extends Command
{
    protected $signature = 'notices:aggregate 
                            {--yesterday : Aggregate yesterday\'s data (default)}
                            {--date= : Aggregate specific date (Y-m-d format)}
                            {--start-date= : Aggregate date range - start date (Y-m-d format)}
                            {--end-date= : Aggregate date range - end date (Y-m-d format)}
                            {--all : Re-aggregate all historical data}';

    protected $description = 'Aggregate notification data into daily summaries';

    public function handle(NotificationAggregatorService $aggregator)
    {
        $this->info('Starting notification aggregation...');

        try {
            if ($this->option('all')) {
                $result = $aggregator->reAggregateAll();
                $this->info("✓ Re-aggregated all historical data");
                $this->line("  Date range: {$result['start_date']} to {$result['end_date']}");
                $this->line("  Combinations: {$result['combinations_aggregated']}");
                
            } elseif ($this->option('start-date') && $this->option('end-date')) {
                $startDate = \Carbon\Carbon::parse($this->option('start-date'));
                $endDate = \Carbon\Carbon::parse($this->option('end-date'));
                $result = $aggregator->aggregateDateRange($startDate, $endDate);
                $this->info("✓ Aggregated date range");
                $this->line("  Date range: {$result['start_date']} to {$result['end_date']}");
                $this->line("  Combinations: {$result['combinations_aggregated']}");
                
            } elseif ($this->option('date')) {
                $date = \Carbon\Carbon::parse($this->option('date'));
                $result = $aggregator->aggregateDate($date);
                $this->info("✓ Aggregated date: {$result['date']}");
                $this->line("  Combinations: {$result['combinations_aggregated']}");
                
            } else {
                // Default: aggregate yesterday
                $result = $aggregator->aggregateYesterday();
                $this->info("✓ Aggregated yesterday's data");
                $this->line("  Date: {$result['date']}");
                $this->line("  Combinations: {$result['combinations_aggregated']}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Aggregation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
