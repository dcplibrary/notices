<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Support\ClusterAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dcplibrary\Notices\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test timezone handling in cluster analysis.
 *
 * CRITICAL: The database stores timestamps in UTC, but Laravel converts to
 * America/Chicago (CST/CDT). This test verifies that cluster counting works
 * correctly despite the timezone difference.
 *
 * Example: A notification exported at 8:04 AM Chicago time on Jan 1, 2026
 * is stored as 14:04 UTC (2026-01-01 14:04:04) in the database.
 * When counting notifications at "8 AM", we must convert to UTC first.
 */
class TimezoneClusterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure timezone is set correctly
        config(['app.timezone' => 'America/Chicago']);
    }

    #[Test]
    public function cluster_analyzer_handles_timezone_conversion_correctly()
    {
        // Create a notification at 8:04 AM Chicago time on Jan 1, 2026
        // This will be stored as 14:04 UTC in the database (CST = UTC-6)
        $notification = Notification::create([
            'notification_key' => 'test-key-1',
            'patron_id' => 1,
            'patron_barcode' => '12345',
            'delivery_option_id' => 2, // Email
            'notification_type' => 'Hold',
            'status' => 'failed',
            'first_exported_at' => '2026-01-01 08:04:04', // Chicago time (will convert to 14:04 UTC)
            'raw_payload' => json_encode([]),
        ]);

        // Create another at 8:30 AM Chicago time (same hour)
        Notification::create([
            'notification_key' => 'test-key-2',
            'patron_id' => 2,
            'patron_barcode' => '12346',
            'delivery_option_id' => 2,
            'notification_type' => 'Hold',
            'status' => 'delivered',
            'first_exported_at' => '2026-01-01 08:30:04', // Chicago time
            'raw_payload' => json_encode([]),
        ]);

        // Create one at 9:15 AM Chicago time (different hour)
        Notification::create([
            'notification_key' => 'test-key-3',
            'patron_id' => 3,
            'patron_barcode' => '12347',
            'delivery_option_id' => 2,
            'notification_type' => 'Hold',
            'status' => 'delivered',
            'first_exported_at' => '2026-01-01 09:15:04', // Chicago time
            'raw_payload' => json_encode([]),
        ]);

        // Test ClusterAnalyzer with Chicago time input (hour 8)
        $date = '2026-01-01';
        $hour = 8; // 8 AM Chicago time

        $failedCount = ClusterAnalyzer::countFailedDeliveries($date, $hour);
        $successCount = ClusterAnalyzer::countSuccessfulDeliveries($date, $hour);

        // Should find 1 failed and 1 successful at 8 AM Chicago time
        $this->assertEquals(1, $failedCount, 'Should count 1 failed notification at 8 AM Chicago time');
        $this->assertEquals(1, $successCount, 'Should count 1 successful notification at 8 AM Chicago time');

        // Verify the notification is actually stored at 14:04 UTC
        $rawTimestamp = \DB::selectOne('SELECT first_exported_at FROM notifications WHERE id = ?', [$notification->id]);
        $this->assertEquals('2026-01-01 14:04:04', $rawTimestamp->first_exported_at,
            'Database should store UTC time (14:04) not Chicago time (08:04)');
    }

    #[Test]
    public function cluster_description_shows_correct_counts()
    {
        // Create notifications at 8 AM Chicago time
        Notification::create([
            'notification_key' => 'test-key-1',
            'patron_id' => 1,
            'patron_barcode' => '12345',
            'delivery_option_id' => 2,
            'notification_type' => 'Hold',
            'status' => 'failed',
            'first_exported_at' => '2026-01-01 08:04:04',
            'raw_payload' => json_encode([]),
        ]);

        // Create 70 successful ones
        for ($i = 0; $i < 70; $i++) {
            Notification::create([
                'notification_key' => 'test-key-success-' . $i,
                'patron_id' => 100 + $i,
                'patron_barcode' => '20000' . $i,
                'delivery_option_id' => 2,
                'notification_type' => 'Hold',
                'status' => 'delivered',
                'first_exported_at' => '2026-01-01 08:' . str_pad($i % 60, 2, '0', STR_PAD_LEFT) . ':04',
                'raw_payload' => json_encode([]),
            ]);
        }

        $description = ClusterAnalyzer::generateClusterDescription('2026-01-01', 8, 1, 70);

        $this->assertStringContainsString('Partial failure', $description);
        $this->assertStringContainsString('1 notification(s) affected', $description);
        $this->assertStringContainsString('70 email(s) sent successfully', $description);
        $this->assertStringNotContainsString('0 notification(s)', $description,
            'Should NOT show 0 notifications (timezone bug indicator)');
    }

    #[Test]
    public function notification_service_get_cluster_info_uses_timezone_aware_counting()
    {
        $notification = Notification::create([
            'notification_key' => 'test-key-1',
            'patron_id' => 1,
            'patron_barcode' => '12345',
            'delivery_option_id' => 2,
            'notification_type' => 'Hold',
            'status' => 'failed',
            'first_exported_at' => '2026-01-01 08:04:04', // Chicago time
            'raw_payload' => json_encode([]),
        ]);

        // Create 5 successful ones
        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'notification_key' => 'test-key-success-' . $i,
                'patron_id' => 100 + $i,
                'patron_barcode' => '20000' . $i,
                'delivery_option_id' => 2,
                'notification_type' => 'Hold',
                'status' => 'delivered',
                'first_exported_at' => '2026-01-01 08:' . (10 + $i) . ':04',
                'raw_payload' => json_encode([]),
            ]);
        }

        // Cluster description moved out of NotificationService into ClusterAnalyzer
        // (same path PatronDeliveryIssue uses for polaris_export_failure details).
        $date = $notification->first_exported_at->format('Y-m-d');
        $hour = (int) $notification->first_exported_at->format('G');

        $failedCount = ClusterAnalyzer::countFailedDeliveries($date, $hour);
        $successCount = ClusterAnalyzer::countSuccessfulDeliveries($date, $hour);
        $clusterInfo = ClusterAnalyzer::generateClusterDescription($date, $hour, $failedCount, $successCount);

        $this->assertStringContainsString('1 notification(s) affected', $clusterInfo);
        $this->assertStringContainsString('5 email(s) sent successfully', $clusterInfo);
        $this->assertStringNotContainsString('0 notification(s)', $clusterInfo);
    }

    #[Test]
    public function hour_filter_in_search_handles_timezone_correctly()
    {
        // Create notification at 8 AM Chicago time
        Notification::create([
            'notification_key' => 'test-key-1',
            'patron_id' => 1,
            'patron_barcode' => '12345',
            'delivery_option_id' => 2,
            'notification_type' => 'Hold',
            'status' => 'failed',
            'first_exported_at' => '2026-01-01 08:15:04', // Chicago time (14:15 UTC)
            'raw_payload' => json_encode([]),
        ]);

        // Create one at 9 AM Chicago time
        Notification::create([
            'notification_key' => 'test-key-2',
            'patron_id' => 2,
            'patron_barcode' => '12346',
            'delivery_option_id' => 2,
            'notification_type' => 'Hold',
            'status' => 'failed',
            'first_exported_at' => '2026-01-01 09:15:04', // Chicago time
            'raw_payload' => json_encode([]),
        ]);

        $service = app(NotificationService::class);

        // Search for notifications at hour 8 (Chicago time)
        $results = $service->searchNotifications(['hour' => 8]);

        $this->assertEquals(1, $results->total(), 'Should find 1 notification at 8 AM Chicago time');
        $this->assertEquals('12345', $results->first()->patron_barcode);
    }
}
