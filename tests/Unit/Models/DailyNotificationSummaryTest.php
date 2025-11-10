<?php

namespace Dcplibrary\Notices\Tests\Unit\Models;

use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class DailyNotificationSummaryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_daily_summary()
    {
        $summary = DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'total_sent' => 100,
            'total_success' => 95,
            'total_failed' => 5,
            'success_rate' => 95.00,
            'failure_rate' => 5.00,
        ]);

        $this->assertInstanceOf(DailyNotificationSummary::class, $summary);
        $this->assertEquals(100, $summary->total_sent);
        $this->assertEquals(95, $summary->total_success);
    }

    /** @test */
    public function it_casts_summary_date_to_date()
    {
        $summary = DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'total_sent' => 100,
        ]);

        $this->assertInstanceOf(Carbon::class, $summary->summary_date);
        $this->assertEquals('2025-11-08', $summary->summary_date->format('Y-m-d'));
    }

    /** @test */
    public function it_casts_rates_to_decimal()
    {
        $summary = DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'success_rate' => 95.50,
            'failure_rate' => 4.50,
            'total_sent' => 100,
        ]);

        $this->assertEquals('95.50', $summary->success_rate);
        $this->assertEquals('4.50', $summary->failure_rate);
    }

    /** @test */
    public function it_can_filter_by_date_range()
    {
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-01',
            'total_sent' => 100,
        ]);
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-05',
            'total_sent' => 150,
        ]);
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-10',
            'total_sent' => 200,
        ]);

        $results = DailyNotificationSummary::dateRange(
            Carbon::parse('2025-11-04'),
            Carbon::parse('2025-11-09')
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals(150, $results->first()->total_sent);
    }

    /** @test */
    public function it_can_filter_by_notification_type()
    {
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'notification_type_id' => 4,
            'total_sent' => 100,
        ]);
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'notification_type_id' => 5,
            'total_sent' => 150,
        ]);

        $results = DailyNotificationSummary::ofType(4)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(4, $results->first()->notification_type_id);
    }

    /** @test */
    public function it_can_get_summary_for_specific_date()
    {
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'total_sent' => 100,
        ]);
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-09',
            'total_sent' => 150,
        ]);

        $results = DailyNotificationSummary::forDate(Carbon::parse('2025-11-08'))->get();

        $this->assertCount(1, $results);
        $this->assertEquals(100, $results->first()->total_sent);
    }

    /** @test */
    public function it_can_get_aggregated_totals()
    {
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-05',
            'total_sent' => 100,
            'total_success' => 90,
            'total_failed' => 10,
            'total_holds' => 50,
            'total_overdues' => 40,
            'success_rate' => 90.00,
        ]);
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-06',
            'total_sent' => 150,
            'total_success' => 140,
            'total_failed' => 10,
            'total_holds' => 70,
            'total_overdues' => 60,
            'success_rate' => 93.33,
        ]);

        $totals = DailyNotificationSummary::getAggregatedTotals(
            Carbon::parse('2025-11-05'),
            Carbon::parse('2025-11-06')
        );

        $this->assertEquals(250, $totals['total_sent']); // 100 + 150
        $this->assertEquals(230, $totals['total_success']); // 90 + 140
        $this->assertEquals(20, $totals['total_failed']); // 10 + 10
        $this->assertEquals(120, $totals['total_holds']); // 50 + 70
        $this->assertEquals(100, $totals['total_overdues']); // 40 + 60
    }

    /** @test */
    public function it_can_get_breakdown_by_type()
    {
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'notification_type_id' => 4, // Holds
            'total_sent' => 100,
            'total_success' => 95,
            'total_failed' => 5,
        ]);
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'notification_type_id' => 5, // Overdues
            'total_sent' => 50,
            'total_success' => 45,
            'total_failed' => 5,
        ]);
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'notification_type_id' => 4, // Holds (another record)
            'total_sent' => 25,
            'total_success' => 20,
            'total_failed' => 5,
        ]);

        $breakdown = DailyNotificationSummary::getBreakdownByType(
            Carbon::parse('2025-11-08'),
            Carbon::parse('2025-11-08')
        );

        $this->assertCount(2, $breakdown);

        // Find the holds type (should have aggregated totals)
        $holdsBreakdown = collect($breakdown)->firstWhere('notification_type_id', 4);
        $this->assertEquals(125, $holdsBreakdown['total_sent']); // 100 + 25
        $this->assertEquals(115, $holdsBreakdown['total_success']); // 95 + 20
    }

    /** @test */
    public function it_can_get_breakdown_by_delivery_method()
    {
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'delivery_option_id' => 2, // Email
            'total_sent' => 100,
            'total_success' => 95,
            'total_failed' => 5,
        ]);
        DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'delivery_option_id' => 3, // SMS
            'total_sent' => 50,
            'total_success' => 48,
            'total_failed' => 2,
        ]);

        $breakdown = DailyNotificationSummary::getBreakdownByDelivery(
            Carbon::parse('2025-11-08'),
            Carbon::parse('2025-11-08')
        );

        $this->assertCount(2, $breakdown);

        $emailBreakdown = collect($breakdown)->firstWhere('delivery_option_id', 2);
        $this->assertEquals(100, $emailBreakdown['total_sent']);
        $this->assertEquals(95, $emailBreakdown['total_success']);
    }

    /** @test */
    public function it_returns_notification_type_name()
    {
        $summary = DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'notification_type_id' => 4,
            'total_sent' => 100,
        ]);

        $this->assertIsString($summary->notification_type_name);
    }

    /** @test */
    public function it_returns_delivery_method_name()
    {
        $summary = DailyNotificationSummary::create([
            'summary_date' => '2025-11-08',
            'delivery_option_id' => 2,
            'total_sent' => 100,
        ]);

        $this->assertIsString($summary->delivery_method_name);
    }
}
