<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Support\DeliveryMethodHelper;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test suite for orphaned notification detection and processing.
 *
 * Verifies that:
 * 1. Orphaned notifications are correctly identified (email, pending >24h, exported)
 * 2. Processing marks them as failed with appropriate issue records
 * 3. Cluster context is accurately generated
 * 4. Partial vs complete failures are distinguished
 * 5. Query logic correctly filters orphaned vs non-orphaned
 */
class OrphanedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);

        $this->markTestSkipped(
            'Orphaned notification processing is disabled as of 2026-01-04. ' .
            'See NotificationService::processOrphanedNotifications() for details.'
        );
    }

    /**
     * Test that orphaned notifications are correctly identified.
     *
     * Orphaned = email, pending >24h, has first_exported_at
     */
    public function test_identifies_orphaned_notifications(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        // Create orphaned notification (exported 26 hours ago, still pending)
        $orphaned = Notification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $now->copy()->subHours(26),
            'first_exported_at' => $now->copy()->subHours(26),
            'is_combined' => false,
        ]);

        // Create non-orphaned: recent pending
        $recent = Notification::create([
            'patron_id' => 1002,
            'patron_barcode' => '20000001002',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $now->copy()->subHours(2),
            'first_exported_at' => $now->copy()->subHours(2),
            'is_combined' => false,
        ]);

        // Create non-orphaned: delivered
        $delivered = Notification::create([
            'patron_id' => 1003,
            'patron_barcode' => '20000001003',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'delivered',
            'generated_at' => $now->copy()->subHours(26),
            'first_exported_at' => $now->copy()->subHours(26),
            'delivered_at' => $now->copy()->subHours(25),
            'is_combined' => false,
        ]);

        // Create non-orphaned: never exported (not orphaned, this is lx_starter_failure)
        $neverExported = Notification::create([
            'patron_id' => 1004,
            'patron_barcode' => '20000001004',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $now->copy()->subHours(26),
            'first_exported_at' => null, // Never exported
            'is_combined' => false,
        ]);

        $result = $this->service->getOrphanedNotifications(7);

        // Should only find the orphaned notification
        $this->assertEquals(1, $result['total_orphaned']);
        $this->assertCount(0, $result['clusters']); // No cluster (need minimum 2)
    }

    /**
     * Test that processing orphaned notifications marks them as failed.
     */
    public function test_processing_marks_orphaned_as_failed(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        // Create orphaned notification
        $notification = Notification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $now->copy()->subHours(26),
            'first_exported_at' => $now->copy()->subHours(26),
            'is_combined' => false,
        ]);

        $this->assertEquals('pending', $notification->status);
        $this->assertNull($notification->failed_at);
        $this->assertNull($notification->failure_reason);

        // Process orphaned notifications
        $result = $this->service->processOrphanedNotifications(7);

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(1, $result['issues_created']);

        // Verify notification was marked as failed
        $notification->refresh();
        $this->assertEquals('failed', $notification->status);
        $this->assertNotNull($notification->failed_at);
        $this->assertStringContainsString(
            'Notification exported from Polaris but never sent by LX Starter',
            $notification->failure_reason
        );
    }

    /**
     * Test that polaris_export_failure issues are created with cluster context.
     */
    public function test_creates_issues_with_cluster_context(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        $exportTime = $now->copy()->subHours(26);

        // Create orphaned notification
        $notification = Notification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $exportTime,
            'first_exported_at' => $exportTime,
            'is_combined' => false,
        ]);

        // Process
        $this->service->processOrphanedNotifications(7);

        // Verify issue was created
        $issue = PatronDeliveryIssue::where('issue_type', 'polaris_export_failure')
            ->where('notification_id', $notification->id)
            ->first();

        $this->assertNotNull($issue);
        $this->assertEquals('polaris_export_failure', $issue->issue_type);
        $this->assertEquals($notification->patron_id, $issue->patron_id);
        $this->assertEquals($notification->patron_barcode, $issue->patron_barcode);
        $this->assertNotNull($issue->issue_details);
        $this->assertStringContainsString('cluster', strtolower($issue->issue_details));
    }

    /**
     * Test that clusters are correctly formed (minimum 2 notifications).
     */
    public function test_creates_clusters_with_minimum_two_notifications(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        $exportTime = $now->copy()->subHours(26);

        // Create 3 orphaned notifications at the same date and hour
        for ($i = 1; $i <= 3; $i++) {
            Notification::create([
                'patron_id' => 1000 + $i,
                'patron_barcode' => '2330700100' . $i,
                'notification_type' => 'Hold',
                'delivery_option_id' => DeliveryMethodHelper::EMAIL,
                'status' => 'pending',
                'generated_at' => $exportTime->copy()->addMinutes($i),
                'first_exported_at' => $exportTime->copy()->addMinutes($i),
                'is_combined' => false,
            ]);
        }

        // Create 1 orphaned at different hour (should not cluster)
        Notification::create([
            'patron_id' => 1004,
            'patron_barcode' => '20000001004',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $exportTime->copy()->addHours(1),
            'first_exported_at' => $exportTime->copy()->addHours(1),
            'is_combined' => false,
        ]);

        $result = $this->service->getOrphanedNotifications(7);

        $this->assertEquals(4, $result['total_orphaned']);
        $this->assertCount(1, $result['clusters']); // Only one cluster (3 notifications)

        $cluster = $result['clusters']->first();
        $this->assertEquals(3, $cluster['count']);
        $this->assertEquals($exportTime->format('Y-m-d'), $cluster['date']);
    }

    /**
     * Test detection of partial vs complete failures.
     */
    public function test_detects_partial_vs_complete_failures(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        $exportTime = $now->copy()->subHours(26);

        // Scenario 1: Complete failure - all pending at same time
        // Create 2 pending (orphaned)
        for ($i = 1; $i <= 2; $i++) {
            Notification::create([
                'patron_id' => 1000 + $i,
                'patron_barcode' => '2330700100' . $i,
                'notification_type' => 'Hold',
                'delivery_option_id' => DeliveryMethodHelper::EMAIL,
                'status' => 'pending',
                'generated_at' => $exportTime,
                'first_exported_at' => $exportTime,
                'is_combined' => false,
            ]);
        }

        $result = $this->service->getOrphanedNotifications(7);
        $cluster = $result['clusters']->first();

        $this->assertFalse($cluster['partial_failure']);
        $this->assertEquals(0, $cluster['successful_at_same_time']);

        // Scenario 2: Partial failure - some succeeded
        // Add a successful delivery at the same time
        Notification::create([
            'patron_id' => 1003,
            'patron_barcode' => '20000001003',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'delivered',
            'generated_at' => $exportTime,
            'first_exported_at' => $exportTime,
            'delivered_at' => $exportTime->copy()->addMinutes(30),
            'is_combined' => false,
        ]);

        $result = $this->service->getOrphanedNotifications(7);
        $cluster = $result['clusters']->first();

        $this->assertTrue($cluster['partial_failure']);
        $this->assertEquals(1, $cluster['successful_at_same_time']);
    }

    /**
     * Test that processed notifications show up in orphaned query.
     */
    public function test_processed_orphaned_appear_in_query(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        $exportTime = $now->copy()->subHours(26);

        // Create orphaned notification
        $notification = Notification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $exportTime,
            'first_exported_at' => $exportTime,
            'is_combined' => false,
        ]);

        // Process it (marks as failed, creates issue)
        $this->service->processOrphanedNotifications(7);

        $notification->refresh();
        $this->assertEquals('failed', $notification->status);

        // Verify it still appears in orphaned query
        $result = $this->service->getOrphanedNotifications(7);
        $this->assertEquals(1, $result['total_orphaned']);
    }

    /**
     * Test that duplicate processing doesn't create duplicate issues.
     */
    public function test_prevents_duplicate_issues(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        $exportTime = $now->copy()->subHours(26);

        // Create orphaned notification
        $notification = Notification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $exportTime,
            'first_exported_at' => $exportTime,
            'is_combined' => false,
        ]);

        // Process once
        $result1 = $this->service->processOrphanedNotifications(7);
        $this->assertEquals(1, $result1['processed']);
        $this->assertEquals(1, $result1['issues_created']);

        // Process again - should not create duplicate
        $result2 = $this->service->processOrphanedNotifications(7);
        $this->assertEquals(0, $result2['processed']); // Already failed
        $this->assertEquals(0, $result2['issues_created']);

        // Verify only one issue exists
        $issueCount = PatronDeliveryIssue::where('issue_type', 'polaris_export_failure')
            ->where('notification_id', $notification->id)
            ->count();

        $this->assertEquals(1, $issueCount);
    }

    /**
     * Test cluster context includes correct time format (12-hour).
     */
    public function test_cluster_context_uses_12_hour_time(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        // Create orphaned at 2 PM (14:00)
        $exportTime = $now->copy()->subHours(26)->setTime(14, 30, 0);

        $notification = Notification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $exportTime,
            'first_exported_at' => $exportTime,
            'is_combined' => false,
        ]);

        $this->service->processOrphanedNotifications(7);

        $issue = PatronDeliveryIssue::where('issue_type', 'polaris_export_failure')
            ->where('notification_id', $notification->id)
            ->first();

        // Should show "2:00-2:59 PM" not "14:00-14:59"
        $this->assertStringContainsString('2:00-2:59 PM', $issue->issue_details);
        $this->assertStringNotContainsString('14:', $issue->issue_details);
    }

    /**
     * Test that only email notifications are processed as orphaned.
     */
    public function test_only_processes_email_notifications(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        $exportTime = $now->copy()->subHours(26);

        // Create pending notifications for each delivery method
        $deliveryMethods = [
            DeliveryMethodHelper::MAIL => 'Mail',
            DeliveryMethodHelper::EMAIL => 'Email',
            DeliveryMethodHelper::VOICE => 'Voice',
            DeliveryMethodHelper::SMS => 'SMS',
        ];

        foreach ($deliveryMethods as $methodId => $methodName) {
            Notification::create([
                'patron_id' => 1000 + $methodId,
                'patron_barcode' => '2330700100' . $methodId,
                'notification_type' => 'Hold',
                'delivery_option_id' => $methodId,
                'status' => 'pending',
                'generated_at' => $exportTime,
                'first_exported_at' => $exportTime,
                'is_combined' => false,
            ]);
        }

        $result = $this->service->processOrphanedNotifications(7);

        // Should only process email (delivery_option_id = 2)
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(1, $result['issues_created']);

        // Verify only email was marked as failed
        $failedCount = Notification::where('status', 'failed')->count();
        $this->assertEquals(1, $failedCount);

        $failed = Notification::where('status', 'failed')->first();
        $this->assertEquals(DeliveryMethodHelper::EMAIL, $failed->delivery_option_id);
    }

    /**
     * Test pattern analysis identifies time-based patterns.
     */
    public function test_pattern_analysis_identifies_time_patterns(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        // Create 10 orphaned at 2 PM
        for ($i = 1; $i <= 10; $i++) {
            $exportTime = $now->copy()->subDays($i)->setTime(14, rand(0, 59), 0);
            Notification::create([
                'patron_id' => 1000 + $i,
                'patron_barcode' => '2330700100' . $i,
                'notification_type' => 'Hold',
                'delivery_option_id' => DeliveryMethodHelper::EMAIL,
                'status' => 'pending',
                'generated_at' => $exportTime,
                'first_exported_at' => $exportTime,
                'is_combined' => false,
            ]);
        }

        // Create 2 orphaned at 3 PM
        for ($i = 11; $i <= 12; $i++) {
            $exportTime = $now->copy()->subDays($i - 10)->setTime(15, rand(0, 59), 0);
            Notification::create([
                'patron_id' => 1000 + $i,
                'patron_barcode' => '2330700100' . $i,
                'notification_type' => 'Hold',
                'delivery_option_id' => DeliveryMethodHelper::EMAIL,
                'status' => 'pending',
                'generated_at' => $exportTime,
                'first_exported_at' => $exportTime,
                'is_combined' => false,
            ]);
        }

        $result = $this->service->getOrphanedNotifications(7);

        $this->assertTrue($result['pattern_analysis']['has_pattern']);
        $this->assertStringContainsString('2:00-2:59 PM', $result['pattern_analysis']['primary_time']);
        $this->assertStringContainsString('pattern', strtolower($result['pattern_analysis']['description']));
    }

    /**
     * Test that days back parameter correctly limits results.
     */
    public function test_days_back_parameter_limits_results(): void
    {
        $now = Carbon::parse('2025-01-20 15:00:00');
        Carbon::setTestNow($now);

        // Create orphaned 3 days ago
        Notification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $now->copy()->subDays(3),
            'first_exported_at' => $now->copy()->subDays(3),
            'is_combined' => false,
        ]);

        // Create orphaned 10 days ago
        Notification::create([
            'patron_id' => 1002,
            'patron_barcode' => '20000001002',
            'notification_type' => 'Hold',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'status' => 'pending',
            'generated_at' => $now->copy()->subDays(10),
            'first_exported_at' => $now->copy()->subDays(10),
            'is_combined' => false,
        ]);

        // Query last 7 days
        $result7 = $this->service->getOrphanedNotifications(7);
        $this->assertEquals(1, $result7['total_orphaned']);

        // Query last 14 days
        $result14 = $this->service->getOrphanedNotifications(14);
        $this->assertEquals(2, $result14['total_orphaned']);
    }
}
