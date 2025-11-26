<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_and_aggregate_notifications()
    {
        // Create sample notifications for a specific date
        $testDate = Carbon::parse('2025-11-08');

        // Email holds - 3 successful, 1 failed
        NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => $testDate,
            'notification_type_id' => 4, // Holds
            'delivery_option_id' => 2, // Email
            'notification_status_id' => 12, // Success
            'holds_count' => 2,
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'notification_date' => $testDate,
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'notification_status_id' => 12,
            'holds_count' => 1,
        ]);
        NotificationLog::create([
            'patron_id' => 102,
            'notification_date' => $testDate,
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'notification_status_id' => 12,
            'holds_count' => 3,
        ]);
        NotificationLog::create([
            'patron_id' => 103,
            'notification_date' => $testDate,
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'notification_status_id' => 14, // Failed
            'holds_count' => 1,
        ]);

        // SMS overdues - 2 successful
        NotificationLog::create([
            'patron_id' => 200,
            'notification_date' => $testDate,
            'notification_type_id' => 5, // Overdues
            'delivery_option_id' => 3, // SMS
            'notification_status_id' => 12,
            'overdues_count' => 2,
        ]);
        NotificationLog::create([
            'patron_id' => 201,
            'notification_date' => $testDate,
            'notification_type_id' => 5,
            'delivery_option_id' => 3,
            'notification_status_id' => 12,
            'overdues_count' => 1,
        ]);

        // Verify we have 6 total notifications
        $this->assertEquals(6, NotificationLog::count());

        // Verify we can filter by type and status
        $successfulHolds = NotificationLog::ofType(4)->successful()->count();
        $this->assertEquals(3, $successfulHolds);

        $failedHolds = NotificationLog::ofType(4)->failed()->count();
        $this->assertEquals(1, $failedHolds);

        $smsNotifications = NotificationLog::byDeliveryMethod(3)->count();
        $this->assertEquals(2, $smsNotifications);
    }

    /** @test */
    public function it_can_query_notifications_across_multiple_dimensions()
    {
        $testDate = Carbon::parse('2025-11-08');

        // Create diverse notification data
        NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => $testDate,
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'notification_status_id' => 12,
            'holds_count' => 2,
        ]);
        NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => $testDate->copy()->subDay(),
            'notification_type_id' => 5,
            'delivery_option_id' => 3,
            'notification_status_id' => 12,
            'overdues_count' => 1,
        ]);

        // Get all notifications for patron 100
        $patronNotifications = NotificationLog::forPatron(100)->get();
        $this->assertCount(2, $patronNotifications);

        // Get notifications from the last 7 days
        $recentNotifications = NotificationLog::recent(7)->get();
        $this->assertCount(2, $recentNotifications);

        // Get notifications for a specific date range
        $rangeNotifications = NotificationLog::dateRange(
            $testDate->copy()->subDays(2),
            $testDate
        )->get();
        $this->assertCount(2, $rangeNotifications);
    }

    /** @test */
    public function it_can_create_and_query_daily_summaries()
    {
        // Create daily summaries for a week
        $startDate = Carbon::parse('2025-11-01');

        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);

            // Email holds summary
            DailyNotificationSummary::create([
                'summary_date' => $date,
                'notification_type_id' => 4,
                'delivery_option_id' => 2,
                'total_sent' => 100 + ($i * 10),
                'total_success' => 90 + ($i * 9),
                'total_failed' => 10 + $i,
                'total_holds' => 100 + ($i * 10),
                'success_rate' => 90.0,
            ]);

            // SMS overdues summary
            DailyNotificationSummary::create([
                'summary_date' => $date,
                'notification_type_id' => 5,
                'delivery_option_id' => 3,
                'total_sent' => 50 + ($i * 5),
                'total_success' => 45 + ($i * 4),
                'total_failed' => 5 + $i,
                'total_overdues' => 50 + ($i * 5),
                'success_rate' => 88.0,
            ]);
        }

        // Verify total count
        $this->assertEquals(14, DailyNotificationSummary::count());

        // Get summaries for specific date range
        $weekSummaries = DailyNotificationSummary::dateRange(
            $startDate,
            $startDate->copy()->addDays(6)
        )->get();
        $this->assertCount(14, $weekSummaries);

        // Get breakdown by notification type
        $typeBreakdown = DailyNotificationSummary::getBreakdownByType(
            $startDate,
            $startDate->copy()->addDays(6)
        );
        $this->assertCount(2, $typeBreakdown);

        // Get breakdown by delivery method
        $deliveryBreakdown = DailyNotificationSummary::getBreakdownByDelivery(
            $startDate,
            $startDate->copy()->addDays(6)
        );
        $this->assertCount(2, $deliveryBreakdown);
    }

    /** @test */
    public function it_calculates_aggregated_totals_correctly()
    {
        $date1 = Carbon::parse('2025-11-01');
        $date2 = Carbon::parse('2025-11-02');

        DailyNotificationSummary::create([
            'summary_date' => $date1,
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'total_sent' => 100,
            'total_success' => 90,
            'total_failed' => 10,
            'total_holds' => 100,
            'total_overdues' => 0,
            'success_rate' => 90.0,
        ]);

        DailyNotificationSummary::create([
            'summary_date' => $date2,
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'total_sent' => 150,
            'total_success' => 140,
            'total_failed' => 10,
            'total_holds' => 150,
            'total_overdues' => 0,
            'success_rate' => 93.33,
        ]);

        $totals = DailyNotificationSummary::getAggregatedTotals($date1, $date2);

        $this->assertEquals(250, $totals['total_sent']);
        $this->assertEquals(230, $totals['total_success']);
        $this->assertEquals(20, $totals['total_failed']);
        $this->assertEquals(250, $totals['total_holds']);

        // Average success rate
        $this->assertGreaterThan(90, $totals['avg_success_rate']);
        $this->assertLessThan(94, $totals['avg_success_rate']);
    }

    /** @test */
    public function it_can_combine_notification_logs_and_summaries()
    {
        $testDate = Carbon::parse('2025-11-08');

        // Create individual notification logs
        for ($i = 0; $i < 10; $i++) {
            NotificationLog::create([
                'patron_id' => 100 + $i,
                'notification_date' => $testDate,
                'notification_type_id' => 4,
                'delivery_option_id' => 2,
                'notification_status_id' => $i < 9 ? 12 : 14, // 9 success, 1 failed
                'holds_count' => 1,
            ]);
        }

        // Manually calculate what the summary should be
        $successful = NotificationLog::where('notification_date', $testDate)
            ->successful()
            ->count();
        $failed = NotificationLog::where('notification_date', $testDate)
            ->failed()
            ->count();
        $totalHolds = NotificationLog::where('notification_date', $testDate)
            ->sum('holds_count');

        $this->assertEquals(9, $successful);
        $this->assertEquals(1, $failed);
        $this->assertEquals(10, $totalHolds);

        // Create a summary based on the calculations
        DailyNotificationSummary::create([
            'summary_date' => $testDate,
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'total_sent' => $successful + $failed,
            'total_success' => $successful,
            'total_failed' => $failed,
            'total_holds' => $totalHolds,
            'success_rate' => ($successful / ($successful + $failed)) * 100,
        ]);

        // Verify the summary matches the raw data
        $summary = DailyNotificationSummary::forDate($testDate)->first();
        $this->assertEquals(10, $summary->total_sent);
        $this->assertEquals(9, $summary->total_success);
        $this->assertEquals(1, $summary->total_failed);
        $this->assertEquals(90.0, (float) $summary->success_rate);
    }
}
