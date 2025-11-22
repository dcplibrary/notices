<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Seeder;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Services\NotificationAggregatorService;
use Carbon\Carbon;

class NotificationLogSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Creating notification log records...');

        NotificationLog::factory()->count(50)->create();
        NotificationLog::factory()->count(20)->holds()->create();
        NotificationLog::factory()->count(15)->overdues()->create();
        NotificationLog::factory()->count(10)->almostOverdue()->create();
        NotificationLog::factory()->count(30)->email()->create();
        NotificationLog::factory()->count(25)->voice()->create();
        NotificationLog::factory()->count(20)->sms()->create();
        NotificationLog::factory()->count(15)->mail()->create();
        NotificationLog::factory()->count(40)->successful()->create();
        NotificationLog::factory()->count(10)->failed()->create();

        $totalRecords = NotificationLog::count();
        $this->command?->info("Created {$totalRecords} notification log records.");

        $aggregator = app(NotificationAggregatorService::class);
        $firstDate = NotificationLog::min('notification_date');
        $lastDate = NotificationLog::max('notification_date');
        if ($firstDate && $lastDate) {
            $startDate = Carbon::parse($firstDate)->startOfDay();
            $endDate = Carbon::parse($lastDate)->startOfDay();
            $result = $aggregator->aggregateDateRange($startDate, $endDate);
            $this->command?->info("Aggregated {$result['combinations_aggregated']} combinations from {$result['start_date']} to {$result['end_date']}");
        }

        $this->command?->info('✅ Seeding and aggregation complete!');
    }
}
