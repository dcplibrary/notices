<?php

namespace Dcplibrary\Notices\Tests\Feature\Api;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Dcplibrary\Notices\Models\ShoutbombSubmission;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up config for API routes
        config(['notices.api.enabled' => true]);
        config(['notices.api.route_prefix' => 'api/notices']);
        config(['notices.api.middleware' => ['api']]);
    }

    /** @test */
    public function it_verifies_notices_by_patron_barcode()
    {
        $patron = '23307013757366';

        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => $patron,
            'phone' => '555-123-4567',
            'notification_date' => now(),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
            'patron_name' => 'John Doe',
            'item_barcode' => '810045',
            'title' => 'Test Book',
        ]);

        $response = $this->getJson("/api/notices/verification?patron_barcode={$patron}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'notices' => [
                '*' => [
                    'id',
                    'date',
                    'patron',
                    'contact',
                    'item',
                    'notice_type',
                    'verification',
                    'status_message',
                ],
            ],
            'summary' => [
                'total',
                'verified',
                'failed',
                'pending',
            ],
            'filters',
        ]);

        $response->assertJsonPath('summary.total', 1);
        $response->assertJsonPath('notices.0.patron.barcode', $patron);
    }

    /** @test */
    public function it_verifies_notices_by_phone_number()
    {
        $phone = '555-123-4567';

        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'phone' => $phone,
            'notification_date' => now(),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        $response = $this->getJson("/api/notices/verification?phone={$phone}");

        $response->assertStatus(200);
        $response->assertJsonPath('summary.total', 1);
    }

    /** @test */
    public function it_verifies_notices_by_email()
    {
        $email = 'test@example.com';

        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'email' => $email,
            'notification_date' => now(),
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
        ]);

        $response = $this->getJson("/api/notices/verification?email={$email}");

        $response->assertStatus(200);
        $response->assertJsonPath('summary.total', 1);
    }

    /** @test */
    public function it_verifies_notices_by_item_barcode()
    {
        $itemBarcode = '810045';

        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'item_barcode' => $itemBarcode,
            'notification_date' => now(),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        $response = $this->getJson("/api/notices/verification?item_barcode={$itemBarcode}");

        $response->assertStatus(200);
        $response->assertJsonPath('summary.total', 1);
    }

    /** @test */
    public function it_filters_by_date_range()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'notification_date' => now()->subDays(40),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'notification_date' => now()->subDays(5),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        $dateFrom = now()->subDays(10)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $response = $this->getJson("/api/notices/verification?patron_barcode=12345&date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200);
        $response->assertJsonPath('summary.total', 1);
    }

    /** @test */
    public function it_returns_verification_timeline_for_single_notice()
    {
        $noticeDate = now();

        $notice = NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'phone' => '555-123-4567',
            'notification_date' => $noticeDate,
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        ShoutbombSubmission::create([
            'patron_barcode' => '12345',
            'notification_type' => 'holds',
            'submitted_at' => $noticeDate->copy()->addMinutes(2),
            'delivery_type' => 'voice',
        ]);

        $response = $this->getJson("/api/notices/verification/{$notice->id}/timeline");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'notice_id',
            'patron_barcode',
            'notice_date',
            'verification' => [
                'verification',
                'timestamps',
                'status',
                'files',
                'timeline',
            ],
            'status_message',
        ]);

        $response->assertJsonPath('notice_id', $notice->id);
        $response->assertJsonPath('patron_barcode', '12345');
    }

    /** @test */
    public function it_returns_patron_verification_history()
    {
        $patron = '12345';

        // Create multiple notices
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

        $response = $this->getJson("/api/notices/verification/patron/{$patron}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'patron' => [
                'barcode',
                'total_notices',
                'success_rate',
                'last_notice',
            ],
            'notices',
            'statistics' => [
                'by_type',
                'by_method',
            ],
        ]);

        $response->assertJsonPath('patron.barcode', $patron);
        $response->assertJsonPath('patron.total_notices', 2);
    }

    /** @test */
    public function it_returns_failed_notices()
    {
        ShoutbombDelivery::create([
            'patron_barcode' => '111',
            'phone_number' => '555-111-1111',
            'sent_date' => now()->subDays(2),
            'delivery_type' => 'Voice',
            'status' => 'Failed',
            'failure_reason' => 'Invalid phone number',
        ]);

        ShoutbombDelivery::create([
            'patron_barcode' => '222',
            'phone_number' => '555-222-2222',
            'sent_date' => now()->subDay(),
            'delivery_type' => 'SMS',
            'status' => 'Failed',
            'failure_reason' => 'Opted out',
        ]);

        $response = $this->getJson('/api/notices/verification/failures');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'failures',
            'summary' => [
                'total_failed',
                'by_reason',
                'date_range',
            ],
        ]);

        $response->assertJsonPath('summary.total_failed', 2);
    }

    /** @test */
    public function it_filters_failures_by_reason()
    {
        ShoutbombDelivery::create([
            'phone_number' => '555-111-1111',
            'sent_date' => now()->subDays(2),
            'delivery_type' => 'Voice',
            'status' => 'Failed',
            'failure_reason' => 'Invalid phone number',
        ]);

        ShoutbombDelivery::create([
            'phone_number' => '555-222-2222',
            'sent_date' => now()->subDay(),
            'delivery_type' => 'SMS',
            'status' => 'Failed',
            'failure_reason' => 'Opted out',
        ]);

        $response = $this->getJson('/api/notices/verification/failures?reason=Invalid');

        $response->assertStatus(200);
        $response->assertJsonPath('summary.total_failed', 1);
    }

    /** @test */
    public function it_searches_notices_with_query()
    {
        NotificationLog::create([
            'patron_id' => 100,
            'patron_barcode' => '12345',
            'phone' => '555-123-4567',
            'notification_date' => now(),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        NotificationLog::create([
            'patron_id' => 101,
            'patron_barcode' => '99999',
            'phone' => '555-999-9999',
            'notification_date' => now(),
            'notification_type_id' => 2,
            'delivery_option_id' => 3,
        ]);

        $response = $this->getJson('/api/notices/verification/search?q=12345');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'notices',
            'pagination' => [
                'total',
                'limit',
                'offset',
                'showing',
            ],
        ]);

        $this->assertEquals(1, count($response->json('notices')));
    }

    /** @test */
    public function it_searches_with_pagination()
    {
        // Create 25 notices
        for ($i = 0; $i < 25; $i++) {
            NotificationLog::create([
                'patron_id' => 100 + $i,
                'patron_barcode' => '12345' . $i,
                'notification_date' => now()->subDays($i),
                'notification_type_id' => 2,
                'delivery_option_id' => 3,
            ]);
        }

        $response = $this->getJson('/api/notices/verification/search?limit=10&offset=0');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.total', 25);
        $response->assertJsonPath('pagination.limit', 10);
        $response->assertJsonPath('pagination.showing', 10);

        $response2 = $this->getJson('/api/notices/verification/search?limit=10&offset=10');
        $response2->assertJsonPath('pagination.showing', 10);

        $response3 = $this->getJson('/api/notices/verification/search?limit=10&offset=20');
        $response3->assertJsonPath('pagination.showing', 5);
    }

    /** @test */
    public function it_validates_date_parameters()
    {
        $response = $this->getJson('/api/notices/verification?date_from=invalid-date');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_from']);
    }

    /** @test */
    public function it_returns_404_for_non_existent_notice_timeline()
    {
        $response = $this->getJson('/api/notices/verification/99999/timeline');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_includes_complete_verification_status_in_response()
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

        ShoutbombSubmission::create([
            'patron_barcode' => '12345',
            'notification_type' => 'holds',
            'submitted_at' => $noticeDate->copy()->addMinutes(2),
            'delivery_type' => 'voice',
        ]);

        PolarisPhoneNotice::create([
            'patron_barcode' => '12345',
            'item_barcode' => '810045',
            'notice_date' => $noticeDate->format('Y-m-d'),
            'delivery_type' => 'voice',
        ]);

        ShoutbombDelivery::create([
            'phone_number' => '555-123-4567',
            'sent_date' => $noticeDate->copy()->addMinutes(5),
            'delivery_type' => 'Voice',
            'status' => 'Delivered',
        ]);

        $response = $this->getJson("/api/notices/verification?patron_barcode=12345");

        $response->assertStatus(200);
        $response->assertJsonPath('notices.0.verification.verification.created', true);
        $response->assertJsonPath('notices.0.verification.verification.submitted', true);
        $response->assertJsonPath('notices.0.verification.verification.verified', true);
        $response->assertJsonPath('notices.0.verification.verification.delivered', true);
        $response->assertJsonPath('notices.0.verification.verification.overall_status', 'success');

        $this->assertCount(4, $response->json('notices.0.verification.timeline'));
    }
}
