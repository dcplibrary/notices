<?php

namespace Dcplibrary\Notices\Tests\Unit\Services;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\ShoutbombSubmission;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Dcplibrary\Notices\Services\NoticeVerificationService;
use Dcplibrary\Notices\Services\VerificationResult;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class NoticeVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NoticeVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NoticeVerificationService();
    }

    /** @test */
    public function it_verifies_a_complete_successful_notice_lifecycle()
    {
        $noticeDate = Carbon::parse('2025-11-09 14:23:15');

        // Create a notification log (Step 1: Created)
        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '23307013757366',
            'phone' => '555-123-4567',
            'item_barcode' => '810045',
            'title' => 'The Bad Guys in Cut to the chase #13',
            'notification_date' => $noticeDate,
            'notification_type_id' => 2, // Hold Ready
            'delivery_option_id' => 3,   // Voice
            'notification_status_id' => 12, // Success
        ]);

        // Create submission (Step 2: Submitted)
        ShoutbombSubmission::create([
            'patron_barcode' => '23307013757366',
            'phone_number' => '555-123-4567',
            'notification_type' => 'holds',
            'submitted_at' => $noticeDate->copy()->addMinutes(2),
            'source_file' => 'holds_submitted_2025-11-09.txt',
            'delivery_type' => 'voice',
        ]);

        // Create phone notice (Step 3: Verified)
        PolarisPhoneNotice::create([
            'patron_barcode' => '23307013757366',
            'phone_number' => '555-123-4567',
            'item_barcode' => '810045',
            'notice_date' => $noticeDate->format('Y-m-d'),
            'delivery_type' => 'voice',
            'source_file' => 'PhoneNotices.csv',
        ]);

        // Create delivery (Step 4: Delivered)
        ShoutbombDelivery::create([
            'patron_barcode' => '23307013757366',
            'phone_number' => '555-123-4567',
            'sent_date' => $noticeDate->copy()->addMinutes(5),
            'delivery_type' => 'Voice',
            'status' => 'Delivered',
        ]);

        // Verify
        $result = $this->service->verify($notice);

        $this->assertInstanceOf(VerificationResult::class, $result);
        $this->assertTrue($result->created);
        $this->assertTrue($result->submitted);
        $this->assertTrue($result->verified);
        $this->assertTrue($result->delivered);
        $this->assertEquals('success', $result->overall_status);
        $this->assertEquals('Delivered', $result->delivery_status);
        $this->assertCount(4, $result->timeline);
    }

    /** @test */
    public function it_verifies_a_failed_notice()
    {
        $noticeDate = Carbon::parse('2025-11-08 09:15:22');

        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '23307013757366',
            'phone' => '555-123-4567',
            'notification_date' => $noticeDate,
            'notification_type_id' => 1, // Overdue
            'delivery_option_id' => 8,   // SMS
            'notification_status_id' => 14, // Failed
        ]);

        ShoutbombSubmission::create([
            'patron_barcode' => '23307013757366',
            'phone_number' => '555-123-4567',
            'notification_type' => 'overdue',
            'submitted_at' => $noticeDate->copy()->addMinutes(5),
            'delivery_type' => 'text',
        ]);

        ShoutbombDelivery::create([
            'patron_barcode' => '23307013757366',
            'phone_number' => '555-123-4567',
            'sent_date' => $noticeDate->copy()->addMinutes(7),
            'delivery_type' => 'SMS',
            'status' => 'Failed',
            'failure_reason' => 'Patron opted out',
        ]);

        $result = $this->service->verify($notice);

        $this->assertTrue($result->created);
        $this->assertTrue($result->submitted);
        $this->assertTrue($result->delivered);
        $this->assertEquals('failed', $result->overall_status);
        $this->assertEquals('Failed', $result->delivery_status);
        $this->assertEquals('Patron opted out', $result->failure_reason);
    }

    /** @test */
    public function it_handles_pending_notice_not_yet_submitted()
    {
        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '23307013757366',
            'phone' => '555-123-4567',
            'notification_date' => now(),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        $result = $this->service->verify($notice);

        $this->assertTrue($result->created);
        $this->assertFalse($result->submitted);
        $this->assertFalse($result->verified);
        $this->assertFalse($result->delivered);
        $this->assertEquals('pending', $result->overall_status);
    }

    /** @test */
    public function it_handles_partial_notice_submitted_but_not_delivered()
    {
        $noticeDate = now();

        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '23307013757366',
            'phone' => '555-123-4567',
            'notification_date' => $noticeDate,
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        ShoutbombSubmission::create([
            'patron_barcode' => '23307013757366',
            'phone_number' => '555-123-4567',
            'notification_type' => 'holds',
            'submitted_at' => $noticeDate->copy()->addMinutes(2),
            'delivery_type' => 'voice',
        ]);

        $result = $this->service->verify($notice);

        $this->assertTrue($result->created);
        $this->assertTrue($result->submitted);
        $this->assertFalse($result->delivered);
        $this->assertEquals('partial', $result->overall_status);
    }

    /** @test */
    public function it_only_verifies_shoutbomb_deliveries()
    {
        // Email notice (not Shoutbomb)
        $emailNotice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '23307013757366',
            'email' => 'test@example.com',
            'notification_date' => now(),
            'notification_type_id' => 2,
            'delivery_option_id' => 2, // Email
        ]);

        $result = $this->service->verify($emailNotice);

        $this->assertTrue($result->created);
        $this->assertFalse($result->submitted);
        $this->assertCount(1, $result->timeline); // Only "created" step
    }

    /** @test */
    public function it_verifies_notices_by_patron_barcode()
    {
        $patron = '23307013757366';

        // Create multiple notices for the patron
        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => $patron,
            'phone' => '555-123-4567',
            'notification_date' => now()->subDays(5),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => $patron,
            'phone' => '555-123-4567',
            'notification_date' => now()->subDays(3),
            'notification_type_id' => 1,
            'delivery_option_id' => 8,
        ]);

        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => $patron,
            'phone' => '555-123-4567',
            'notification_date' => now()->subDay(),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        $results = $this->service->verifyByPatron($patron);

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('notice', $results[0]);
        $this->assertArrayHasKey('verification', $results[0]);
        $this->assertInstanceOf(VerificationResult::class, $results[0]['verification']);
    }

    /** @test */
    public function it_filters_patron_notices_by_date_range()
    {
        $patron = '23307013757366';
        $startDate = now()->subDays(10);
        $endDate = now()->subDays(5);

        // Create notices outside the range
        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => $patron,
            'notification_date' => now()->subDays(15),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        // Create notices inside the range
        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => $patron,
            'notification_date' => now()->subDays(7),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => $patron,
            'notification_date' => now()->subDays(6),
            'notification_type_id' => 1,
            'delivery_option_id' => 8,
        ]);

        // Create notices outside the range
        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => $patron,
            'notification_date' => now()->subDays(3),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        $results = $this->service->verifyByPatron($patron, $startDate, $endDate);

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_gets_failed_notices()
    {
        $startDate = now()->subDays(7);
        $endDate = now();

        // Create failed deliveries
        ShoutbombDelivery::create([
            'patron_barcode' => '111',
            'phone_number' => '555-111-1111',
            'sent_date' => now()->subDays(3),
            'delivery_type' => 'Voice',
            'status' => 'Failed',
            'failure_reason' => 'Invalid phone number',
        ]);

        ShoutbombDelivery::create([
            'patron_barcode' => '222',
            'phone_number' => '555-222-2222',
            'sent_date' => now()->subDays(2),
            'delivery_type' => 'SMS',
            'status' => 'Failed',
            'failure_reason' => 'Opted out',
        ]);

        // Create successful delivery (should not be included)
        ShoutbombDelivery::create([
            'patron_barcode' => '333',
            'phone_number' => '555-333-3333',
            'sent_date' => now()->subDay(),
            'delivery_type' => 'Voice',
            'status' => 'Delivered',
        ]);

        $failures = $this->service->getFailedNotices($startDate, $endDate);

        $this->assertCount(2, $failures);
    }

    /** @test */
    public function it_filters_failed_notices_by_reason()
    {
        $startDate = now()->subDays(7);
        $endDate = now();

        ShoutbombDelivery::create([
            'phone_number' => '555-111-1111',
            'sent_date' => now()->subDays(3),
            'delivery_type' => 'Voice',
            'status' => 'Failed',
            'failure_reason' => 'Invalid phone number',
        ]);

        ShoutbombDelivery::create([
            'phone_number' => '555-222-2222',
            'sent_date' => now()->subDays(2),
            'delivery_type' => 'SMS',
            'status' => 'Failed',
            'failure_reason' => 'Opted out',
        ]);

        ShoutbombDelivery::create([
            'phone_number' => '555-333-3333',
            'sent_date' => now()->subDay(),
            'delivery_type' => 'Voice',
            'status' => 'Failed',
            'failure_reason' => 'Invalid phone number',
        ]);

        $failures = $this->service->getFailedNotices($startDate, $endDate, 'Invalid');

        $this->assertCount(2, $failures);
    }

    /** @test */
    public function it_matches_submissions_by_patron_and_date()
    {
        $noticeDate = now();

        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'phone' => '555-123-4567',
            'notification_date' => $noticeDate,
            'notification_type_id' => 2, // Hold Ready
            'delivery_option_id' => 3,
        ]);

        // Same patron, same type, same day
        ShoutbombSubmission::create([
            'patron_barcode' => '12345',
            'phone_number' => '555-123-4567',
            'notification_type' => 'holds',
            'submitted_at' => $noticeDate->copy()->addMinutes(2),
            'delivery_type' => 'voice',
        ]);

        // Different patron
        ShoutbombSubmission::create([
            'patron_barcode' => '99999',
            'phone_number' => '555-999-9999',
            'notification_type' => 'holds',
            'submitted_at' => $noticeDate->copy()->addMinutes(2),
            'delivery_type' => 'voice',
        ]);

        $result = $this->service->verify($notice);

        $this->assertTrue($result->submitted);
    }

    /** @test */
    public function it_matches_phone_notices_by_patron_item_and_date()
    {
        $noticeDate = now();

        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'phone' => '555-123-4567',
            'item_barcode' => '810045',
            'notification_date' => $noticeDate,
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        // Matching phone notice
        PolarisPhoneNotice::create([
            'patron_barcode' => '12345',
            'phone_number' => '555-123-4567',
            'item_barcode' => '810045',
            'notice_date' => $noticeDate->format('Y-m-d'),
            'delivery_type' => 'voice',
        ]);

        $result = $this->service->verify($notice);

        $this->assertTrue($result->verified);
    }

    /** @test */
    public function it_matches_deliveries_within_time_window()
    {
        $noticeDate = Carbon::parse('2025-11-09 14:23:15');

        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'phone' => '555-123-4567',
            'notification_date' => $noticeDate,
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        // Delivery within 24-hour window
        ShoutbombDelivery::create([
            'phone_number' => '555-123-4567',
            'sent_date' => $noticeDate->copy()->addMinutes(30),
            'delivery_type' => 'Voice',
            'status' => 'Delivered',
        ]);

        $result = $this->service->verify($notice);

        $this->assertTrue($result->delivered);
    }
}
