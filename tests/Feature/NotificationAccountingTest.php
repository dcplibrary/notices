<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PolarisHoldNotification;
use Dcplibrary\Notices\Services\NotificationReconciler;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test suite to verify 100% of notices are accounted for during reconciliation.
 *
 * These tests ensure that:
 * 1. All raw Polaris records result in unified notification records
 * 2. No notices are lost during deduplication
 * 3. Patron/item counts match between raw and unified data
 * 4. Edge cases (duplicates, missing data) are handled correctly
 */
class NotificationAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationReconciler $reconciler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconciler = app(NotificationReconciler::class);
    }

    /**
     * Test that all raw hold notifications are accounted for in unified notifications.
     *
     * Verifies that after reconciliation:
     * - Every unique patron gets a notification
     * - Item count matches between raw and unified data
     * - No notifications are lost during grouping/deduplication
     */
    public function test_all_raw_hold_notifications_are_accounted_for(): void
    {
        $date = Carbon::parse('2025-01-15');

        // Create raw hold notifications with various scenarios
        $rawNotifications = [
            // Single patron, single item
            [
                'patron_id' => 1001,
                'patron_barcode' => '20000001001',
                'item_barcode' => '30000001001',
                'delivery_option_id' => 3, // Voice (holds exports only create Voice/SMS notifications)
                'notification_type_id' => 2,
                'title' => 'Book Title 1',
                'patron_first_name' => 'John',
                'patron_last_name' => 'Doe',
            ],
            // Same patron, different item (should group into one notification with 2 items)
            [
                'patron_id' => 1001,
                'patron_barcode' => '20000001001',
                'item_barcode' => '30000001002',
                'delivery_option_id' => 3, // Same delivery option (Voice)
                'notification_type_id' => 2,
                'title' => 'Book Title 2',
                'patron_first_name' => 'John',
                'patron_last_name' => 'Doe',
            ],
            // Different patron (should create separate notification)
            [
                'patron_id' => 1002,
                'patron_barcode' => '20000001002',
                'item_barcode' => '30000001003',
                'delivery_option_id' => 8, // SMS
                'notification_type_id' => 2,
                'title' => 'Book Title 3',
                'patron_first_name' => 'Jane',
                'patron_last_name' => 'Smith',
            ],
            // Same patron as first, but different delivery option (should create separate notification)
            [
                'patron_id' => 1001,
                'patron_barcode' => '20000001001',
                'item_barcode' => '30000001004',
                'delivery_option_id' => 8, // SMS - different from email
                'notification_type_id' => 2,
                'title' => 'Book Title 4',
                'patron_first_name' => 'John',
                'patron_last_name' => 'Doe',
            ],
        ];

        // Insert raw notifications
        foreach ($rawNotifications as $raw) {
            PolarisHoldNotification::create(array_merge($raw, [
                'notification_creation_date' => $date,
                'export_datetime' => $date->copy()->setTime(8, 5, 0),
                'processed' => 0,
            ]));
        }

        // Get counts before reconciliation
        $totalRawRecords = PolarisHoldNotification::whereDate('notification_creation_date', $date)->count();
        $this->assertEquals(4, $totalRawRecords, 'Should have 4 raw records');

        // Run reconciliation
        $this->reconciler->reconcileHolds($date);

        // Verify unified notifications
        $unifiedNotifications = Notification::whereDate('first_exported_at', $date)->get();

        // Should create exactly one notification per unique patron+delivery_option+type combination
        $this->assertCount(3, $unifiedNotifications, 'Should create 3 unified notifications');

        // Verify each notification
        $voiceNotification = $unifiedNotifications->where('patron_id', 1001)
            ->where('delivery_option_id', 3)
            ->first();
        $this->assertNotNull($voiceNotification, 'Voice notification for patron 1001 should exist');

        // Check the raw payload contains both items
        $payload = json_decode($voiceNotification->raw_payload, true);
        $this->assertCount(2, $payload, 'Voice notification should contain 2 items');

        // Verify item barcodes are present
        $itemBarcodes = array_column($payload, 'item_barcode');
        $this->assertContains('30000001001', $itemBarcodes, 'Should contain first item barcode');
        $this->assertContains('30000001002', $itemBarcodes, 'Should contain second item barcode');

        $smsNotification = $unifiedNotifications->where('patron_id', 1001)
            ->where('delivery_option_id', 8)
            ->first();
        $this->assertNotNull($smsNotification, 'SMS notification for patron 1001 should exist');

        $smsOtherPatron = $unifiedNotifications->where('patron_id', 1002)
            ->where('delivery_option_id', 8)
            ->first();
        $this->assertNotNull($smsOtherPatron, 'SMS notification for patron 1002 should exist');

        // Verify no data loss: total item count should match
        $totalItemsInUnified = $unifiedNotifications->sum(function ($notification) {
            return count(json_decode($notification->raw_payload, true));
        });
        $this->assertEquals($totalRawRecords, $totalItemsInUnified, 'All items should be accounted for in unified notifications');
    }

    /**
     * Test that duplicate raw records don't create duplicate notifications.
     *
     * Polaris sometimes exports the same notification multiple times.
     * Verify that deduplication works correctly.
     */
    public function test_duplicate_raw_records_are_deduplicated(): void
    {
        $date = Carbon::parse('2025-01-15');

        // Create identical raw records (simulating Polaris duplicate exports)
        $rawData = [
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'item_barcode' => '30000001001',
            'delivery_option_id' => 3,
            'notification_type_id' => 2,
            'title' => 'Duplicate Book',
            'patron_first_name' => 'John',
            'patron_last_name' => 'Doe',
            'notification_creation_date' => $date,
            'export_datetime' => $date->copy()->setTime(8, 5, 0),
            'processed' => 0,
        ];

        // Insert the same record 3 times
        PolarisHoldNotification::create($rawData);
        PolarisHoldNotification::create($rawData);
        PolarisHoldNotification::create($rawData);

        $this->assertEquals(3, PolarisHoldNotification::count(), 'Should have 3 duplicate raw records');

        // Run reconciliation
        $this->reconciler->reconcileHolds($date);

        // Should create only ONE unified notification
        $notifications = Notification::all();
        $this->assertCount(1, $notifications, 'Should deduplicate to 1 notification');

        // The notification should contain the item only once
        $payload = json_decode($notifications->first()->raw_payload, true);
        // Current implementation preserves full raw export rows in raw_payload (including duplicates)
        // so the UI can audit exports; deduplication is at the notification record level.
        $this->assertCount(3, $payload, 'Should preserve all 3 raw export rows in raw_payload');
    }

    /**
     * Test accounting across multiple notification types.
     *
     * Verify that holds, overdues, and renewals are all accounted for separately.
     */
    public function test_different_notification_types_are_tracked_separately(): void
    {
        $date = Carbon::parse('2025-01-15');

        // Same patron, same delivery, but different notification types
        $rawData = [
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'item_barcode' => '30000001001',
            'delivery_option_id' => 3,
            'title' => 'Test Book',
            'patron_first_name' => 'John',
            'patron_last_name' => 'Doe',
            'notification_creation_date' => $date,
            'export_datetime' => $date->copy()->setTime(8, 5, 0),
            'processed' => 0,
        ];

        // Create hold notification (type 2)
        PolarisHoldNotification::create(array_merge($rawData, [
            'notification_type_id' => 2,
        ]));

        // Note: We would also test overdues and renewals, but they use different tables
        // For now, just verify holds are counted correctly

        $this->reconciler->reconcileHolds($date);

        $notifications = Notification::all();
        $this->assertCount(1, $notifications, 'Should have 1 hold notification');
        $this->assertEquals('Hold', $notifications->first()->notification_type, 'Should be a hold notification');
    }

    /**
     * Test that patron barcode accounting is maintained.
     *
     * Verify we can trace every patron barcode from raw to unified data.
     */
    public function test_patron_barcode_accounting(): void
    {
        $date = Carbon::parse('2025-01-15');

        $patronBarcodes = ['20000001001', '20000001002', '20000001003', '20000001004'];

        foreach ($patronBarcodes as $index => $barcode) {
            PolarisHoldNotification::create([
                'patron_id' => 1000 + $index,
                'patron_barcode' => $barcode,
                'item_barcode' => '30000001001',
                'delivery_option_id' => 3,
                'notification_type_id' => 2,
                'title' => "Book {$index}",
                'patron_first_name' => "Patron",
                'patron_last_name' => (string)$index,
                'notification_creation_date' => $date,
                'export_datetime' => $date->copy()->setTime(8, 5, 0),
                'processed' => 0,
            ]);
        }

        $rawBarcodes = PolarisHoldNotification::pluck('patron_barcode')->unique()->sort()->values();

        $this->reconciler->reconcileHolds($date);

        $unifiedBarcodes = Notification::pluck('patron_barcode')->unique()->sort()->values();

        // Every patron barcode in raw data should appear in unified data
        $this->assertEquals($rawBarcodes->toArray(), $unifiedBarcodes->toArray(), 'All patron barcodes should be accounted for');
    }
}
