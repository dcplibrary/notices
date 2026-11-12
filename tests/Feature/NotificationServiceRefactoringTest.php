<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Models\PolarisHoldNotification;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature tests for refactored NotificationService methods.
 *
 * Tests the new methods added during refactoring:
 * - findPatronByIdOrBarcode()
 * - getMismatchedPreferenceConfirmation()
 * - buildFailedContactApiResponse()
 */
class NotificationServiceRefactoringTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    /**
     * Test finding patron by patron_id.
     */
    public function test_find_patron_by_id(): void
    {
        PolarisHoldNotification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'patron_first_name' => 'John',
            'patron_last_name' => 'Doe',
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
            'export_datetime' => now(),
        ]);

        $patron = $this->service->findPatronByIdOrBarcode(1001, null);

        $this->assertNotNull($patron);
        $this->assertEquals(1001, $patron->patron_id);
        $this->assertEquals('20000001001', $patron->patron_barcode);
        $this->assertEquals('John', $patron->patron_first_name);
        $this->assertEquals('Doe', $patron->patron_last_name);
    }

    /**
     * Test finding patron by barcode when patron_id not found.
     */
    public function test_find_patron_by_barcode_fallback(): void
    {
        PolarisHoldNotification::create([
            'patron_id' => null,
            'patron_barcode' => '20000001001',
            'patron_first_name' => 'Jane',
            'patron_last_name' => 'Smith',
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
            'export_datetime' => now(),
        ]);

        $patron = $this->service->findPatronByIdOrBarcode(null, '20000001001');

        $this->assertNotNull($patron);
        $this->assertEquals('20000001001', $patron->patron_barcode);
        $this->assertEquals('Jane', $patron->patron_first_name);
    }

    /**
     * Test finding patron returns null when not found.
     */
    public function test_find_patron_returns_null_when_not_found(): void
    {
        $patron = $this->service->findPatronByIdOrBarcode(9999, '99999999999');

        $this->assertNull($patron);
    }

    /**
     * Test getting mismatched preference confirmation.
     */
    public function test_get_mismatched_preference_confirmation(): void
    {
        DeliveryConfirmation::create([
            'confirmation_source' => 'shoutbomb_mismatched_preference',
            'patron_barcode' => '20000001001',
            'delivery_string' => '5555312217',
            'delivery_option_id' => 3,
            'notification_id' => null,
            'notice_date' => now(),
        ]);

        $confirmation = $this->service->getMismatchedPreferenceConfirmation('20000001001');

        $this->assertNotNull($confirmation);
        $this->assertEquals('5555312217', $confirmation->delivery_string);
        $this->assertEquals(3, $confirmation->delivery_option_id);
    }

    /**
     * Test getting mismatched preference confirmation returns null when not found.
     */
    public function test_get_mismatched_preference_confirmation_returns_null(): void
    {
        $confirmation = $this->service->getMismatchedPreferenceConfirmation('99999999999');

        $this->assertNull($confirmation);
    }

    /**
     * Test building failed contact API response.
     */
    public function test_build_failed_contact_api_response(): void
    {
        $patron = (object) [
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'patron_first_name' => 'John',
            'patron_last_name' => 'Doe',
        ];

        $response = $this->service->buildFailedContactApiResponse([
            'id' => 123,
            'delivery_string' => '5555312217',
            'string_type' => 'phone',
            'delivery_option_id' => 3,
            'delivery_option_label' => 'Voice',
            'failure_source' => 'Mismatched Preference',
            'failure_details' => 'Test details',
            'failure_count' => 1,
            'affected_patron_count' => 1,
            'first_failed_at' => Carbon::parse('2026-01-15'),
            'last_failed_at' => Carbon::parse('2026-01-15'),
            'is_resolved' => false,
            'resolved_at' => null,
            'resolution_notes' => null,
            'patron' => $patron,
        ]);

        $this->assertEquals(123, $response['id']);
        $this->assertEquals('5555312217', $response['delivery_string']);
        $this->assertEquals('phone', $response['string_type']);
        $this->assertEquals(3, $response['delivery_option_id']);
        $this->assertEquals('Voice', $response['delivery_option_label']);
        $this->assertEquals('Mismatched Preference', $response['failure_source']);
        $this->assertEquals('Jan 15, 2026', $response['first_failed_at']);
        $this->assertEquals('Jan 15, 2026', $response['last_failed_at']);
        $this->assertFalse($response['is_resolved']);
        $this->assertNull($response['resolved_at']);
        $this->assertNotNull($response['patron']);
        $this->assertEquals(1001, $response['patron']['patron_id']);
        $this->assertArrayHasKey('delivery_labels', $response);
        $this->assertArrayHasKey('email', $response['delivery_labels']);
        $this->assertArrayHasKey('mail', $response['delivery_labels']);
    }

    /**
     * Test building failed contact API response with null values.
     */
    public function test_build_failed_contact_api_response_with_nulls(): void
    {
        $response = $this->service->buildFailedContactApiResponse([
            'id' => 123,
            'delivery_string' => '5555312217',
            'string_type' => 'phone',
            'delivery_option_id' => 3,
            'delivery_option_label' => 'Voice',
            'failure_source' => 'Test',
            'first_failed_at' => null,
            'last_failed_at' => null,
            'is_resolved' => false,
            'resolved_at' => null,
            'patron' => null,
        ]);

        $this->assertNull($response['first_failed_at']);
        $this->assertNull($response['last_failed_at']);
        $this->assertNull($response['resolved_at']);
        $this->assertNull($response['patron']);
    }

    /**
     * Test building failed contact API response with resolved status.
     */
    public function test_build_failed_contact_api_response_resolved(): void
    {
        $response = $this->service->buildFailedContactApiResponse([
            'id' => 123,
            'delivery_string' => '5555312217',
            'string_type' => 'phone',
            'delivery_option_id' => 3,
            'delivery_option_label' => 'Voice',
            'failure_source' => 'Test',
            'first_failed_at' => Carbon::parse('2026-01-15'),
            'last_failed_at' => Carbon::parse('2026-01-15'),
            'is_resolved' => true,
            'resolved_at' => Carbon::parse('2026-01-16 14:30:00'),
            'resolution_notes' => 'Fixed phone number',
            'patron' => null,
        ]);

        $this->assertTrue($response['is_resolved']);
        $this->assertEquals('Jan 16, 2026 2:30 PM', $response['resolved_at']);
        $this->assertEquals('Fixed phone number', $response['resolution_notes']);
    }
}
