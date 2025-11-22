<?php

namespace Dcplibrary\Notices\Tests\Unit\Models;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class NotificationLogTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_notification_log()
    {
        $notification = NotificationLog::create([
            'polaris_log_id' => 12345,
            'patron_id' => 100,
            'patron_barcode' => '21234567890',
            'notification_date' => now(),
            'notification_type_id' => 4, // Holds
            'delivery_option_id' => 2, // Email
            'notification_status_id' => 12, // Success
            'holds_count' => 3,
            'overdues_count' => 0,
            'reporting_org_id' => 3,
        ]);

        $this->assertInstanceOf(NotificationLog::class, $notification);
        $this->assertEquals(12345, $notification->polaris_log_id);
        $this->assertEquals(100, $notification->patron_id);
    }

    /** @test */
    public function it_calculates_total_items_correctly()
    {
        $notification = NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => now(),
            'holds_count' => 2,
            'overdues_count' => 3,
            'overdues_2nd_count' => 1,
            'bills_count' => 1,
            'cancels_count' => 0,
            'recalls_count' => 0,
            'overdues_3rd_count' => 0,
            'manual_bill_count' => 0,
        ]);

        // 2 + 3 + 1 + 1 = 7
        $this->assertEquals(7, $notification->total_items);
    }

    /** @test */
    public function it_returns_notification_type_name()
    {
        $notification = NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => now(),
            'notification_type_id' => 4, // Holds (based on config)
        ]);

        $this->assertIsString($notification->notification_type_name);
    }

    /** @test */
    public function it_returns_delivery_method_name()
    {
        $notification = NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => now(),
            'delivery_option_id' => 2, // Email
        ]);

        $this->assertIsString($notification->delivery_method_name);
    }

    /** @test */
    public function it_returns_notification_status_name()
    {
        $notification = NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => now(),
            'notification_status_id' => 12, // Success
        ]);

        $this->assertIsString($notification->notification_status_name);
    }

    /** @test */
    public function it_can_filter_by_date_range()
    {
        // Create notifications on different dates
        NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => Carbon::parse('2025-11-01'),
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'notification_date' => Carbon::parse('2025-11-05'),
        ]);
        NotificationLog::create([
            'patron_id' => 102,
            'notification_date' => Carbon::parse('2025-11-10'),
        ]);

        $results = NotificationLog::dateRange(
            Carbon::parse('2025-11-04'),
            Carbon::parse('2025-11-09')
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals(101, $results->first()->patron_id);
    }

    /** @test */
    public function it_can_filter_by_notification_type()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'notification_type_id' => 4, // Holds
            'notification_date' => now(),
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'notification_type_id' => 5, // Overdues
            'notification_date' => now(),
        ]);

        $results = NotificationLog::ofType(4)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(4, $results->first()->notification_type_id);
    }

    /** @test */
    public function it_can_filter_by_delivery_method()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'delivery_option_id' => 2, // Email
            'notification_date' => now(),
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'delivery_option_id' => 3, // SMS
            'notification_date' => now(),
        ]);

        $results = NotificationLog::byDeliveryMethod(3)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(3, $results->first()->delivery_option_id);
    }

    /** @test */
    public function it_can_filter_successful_notifications()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'notification_status_id' => 12, // Success
            'notification_date' => now(),
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'notification_status_id' => 14, // Failed
            'notification_date' => now(),
        ]);
        NotificationLog::create([
            'patron_id' => 102,
            'notification_status_id' => 12, // Success
            'notification_date' => now(),
        ]);

        $results = NotificationLog::successful()->get();

        $this->assertCount(2, $results);
        $this->assertEquals(12, $results->first()->notification_status_id);
    }

    /** @test */
    public function it_can_filter_failed_notifications()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'notification_status_id' => 12, // Success
            'notification_date' => now(),
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'notification_status_id' => 14, // Failed
            'notification_date' => now(),
        ]);

        $results = NotificationLog::failed()->get();

        $this->assertCount(1, $results);
        $this->assertEquals(14, $results->first()->notification_status_id);
    }

    /** @test */
    public function it_can_filter_by_patron()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => now(),
        ]);
        NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => now()->subDay(),
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'notification_date' => now(),
        ]);

        $results = NotificationLog::forPatron(100)->get();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_filter_recent_notifications()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => now()->subDays(3),
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'notification_date' => now()->subDays(10),
        ]);
        NotificationLog::create([
            'patron_id' => 102,
            'notification_date' => now(),
        ]);

        $results = NotificationLog::recent(7)->get();

        // Should only return notifications from last 7 days
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_casts_notification_date_to_datetime()
    {
        $notification = NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => '2025-11-08 10:30:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $notification->notification_date);
    }

    /** @test */
    public function it_casts_reported_to_boolean()
    {
        $notification = NotificationLog::create([
            'patron_id' => 100,
            'notification_date' => now(),
            'reported' => 1,
        ]);

        $this->assertIsBool($notification->reported);
        $this->assertTrue($notification->reported);
    }

    /** @test */
    public function it_can_chain_multiple_scopes()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'notification_type_id' => 4,
            'delivery_option_id' => 3,
            'notification_status_id' => 12,
            'notification_date' => now(),
        ]);
        NotificationLog::create([
            'patron_id' => 101,
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'notification_status_id' => 12,
            'notification_date' => now(),
        ]);

        $results = NotificationLog::ofType(4)
            ->byDeliveryMethod(3)
            ->successful()
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals(100, $results->first()->patron_id);
    }
}
