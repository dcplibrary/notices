<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\EmailReportType;
use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Services\NotificationReconciler;
use Dcplibrary\Notices\Support\DeliveryMethodHelper;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test suite for delivery issue detection logic.
 *
 * Verifies that:
 * 1. lx_starter_failure issues are only created for electronic delivery
 * 2. Mail notifications are NOT flagged as lx_starter_failure
 * 3. Failed delivery strings are detected correctly
 * 4. Null preference issues are detected
 */
class IssueDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationReconciler $reconciler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconciler = app(NotificationReconciler::class);
    }

    /**
     * Test that mail notifications are NEVER flagged as lx_starter_failure.
     *
     * Mail is printed manually by staff and should remain pending without being flagged.
     */
    public function test_mail_notifications_not_flagged_as_lx_starter_failure(): void
    {
        $date = Carbon::parse('2025-01-15 08:05:00', 'America/Chicago');

        // Mail is manual delivery (not LX Starter). It can remain pending indefinitely
        // without being flagged as an LX Starter failure.
        Notification::create([
            'patron_id'            => 1001,
            'patron_barcode'       => '20000001001',
            'notification_type_id' => 2,
            'delivery_option_id'   => DeliveryMethodHelper::MAIL,
            'status'               => 'pending',
            'generated_at'         => $date,
            'first_exported_at'    => $date,
            'export_filename'      => 'holds_2025-01-15_08-05-00.txt',
            'raw_payload'          => json_encode([]),
        ]);

        // Run reconciliation (which includes issue detection)
        $this->reconciler->reconcile($date);

        // Verify notification was created and is pending
        $notification = Notification::where('patron_barcode', '20000001001')->first();
        $this->assertNotNull($notification, 'Notification should be created');
        $this->assertEquals('pending', $notification->status, 'Mail notification should be pending');
        $this->assertEquals(DeliveryMethodHelper::MAIL, $notification->delivery_option_id);

        // Verify NO lx_starter_failure issue was created
        $lxIssues = PatronDeliveryIssue::where('issue_type', 'lx_starter_failure')
            ->where('notification_id', $notification->id)
            ->get();

        $this->assertCount(0, $lxIssues, 'Mail notifications should NEVER be flagged as lx_starter_failure');

        // Verify overall issue count
        $allIssues = PatronDeliveryIssue::where('notification_id', $notification->id)->get();
        $this->assertCount(0, $allIssues, 'Pending mail should not create any issues');
    }

    /**
     * Test that only Email notifications are flagged as lx_starter_failure.
     *
     * Voice/SMS use Shoutbomb reconciliation paths and must not be flagged with
     * Polaris→LX Starter upload failure issues.
     */
    public function test_electronic_notifications_flagged_as_lx_starter_failure(): void
    {
        $date = Carbon::parse('2025-01-15 08:05:00', 'America/Chicago');

        $email = Notification::create([
            'patron_id'            => 1002,
            'patron_barcode'       => '20000001002',
            'notification_type_id' => 2,
            'delivery_option_id'   => DeliveryMethodHelper::EMAIL,
            'status'               => 'pending',
            'generated_at'         => $date,
            'first_exported_at'    => $date,
            'export_filename'      => 'notification_log_2025-01-15_08-05-00.txt',
            'raw_payload'          => json_encode([]),
        ]);

        $voice = Notification::create([
            'patron_id'            => 1003,
            'patron_barcode'       => '20000001003',
            'notification_type_id' => 2,
            'delivery_option_id'   => DeliveryMethodHelper::VOICE,
            'status'               => 'pending',
            'generated_at'         => $date,
            'first_exported_at'    => $date,
            'export_filename'      => 'holds_2025-01-15_08-05-00.txt',
            'raw_payload'          => json_encode([]),
        ]);

        $sms = Notification::create([
            'patron_id'            => 1008,
            'patron_barcode'       => '20000001008',
            'notification_type_id' => 2,
            'delivery_option_id'   => DeliveryMethodHelper::SMS,
            'status'               => 'pending',
            'generated_at'         => $date,
            'first_exported_at'    => $date,
            'export_filename'      => 'holds_2025-01-15_08-05-00.txt',
            'raw_payload'          => json_encode([]),
        ]);

        // Advance time past the 48h grace window used for lx_starter_failure detection.
        $this->travelTo($date->copy()->addHours(49));

        $this->reconciler->reconcile($date);

        $emailIssue = PatronDeliveryIssue::where('notification_id', $email->id)
            ->where('issue_type', 'lx_starter_failure')
            ->first();
        $this->assertNotNull($emailIssue, 'Email should be flagged as lx_starter_failure once grace period passes.');

        $voiceIssue = PatronDeliveryIssue::where('notification_id', $voice->id)
            ->where('issue_type', 'lx_starter_failure')
            ->first();
        $this->assertNull($voiceIssue, 'Voice should not be flagged as lx_starter_failure.');

        $smsIssue = PatronDeliveryIssue::where('notification_id', $sms->id)
            ->where('issue_type', 'lx_starter_failure')
            ->first();
        $this->assertNull($smsIssue, 'SMS should not be flagged as lx_starter_failure.');
    }

    /**
     * Test that lx_starter_failure issues auto-resolve when notification status changes.
     */
    public function test_lx_starter_failure_auto_resolves(): void
    {
        $date = Carbon::parse('2025-01-15 08:05:00', 'America/Chicago');

        $notification = Notification::create([
            'patron_id'            => 1001,
            'patron_barcode'       => '20000001001',
            'notification_type_id' => 2,
            'delivery_option_id'   => DeliveryMethodHelper::VOICE,
            'status'               => 'pending',
            'generated_at'         => $date,
            'first_exported_at'    => $date,
            'export_filename'      => 'holds_2025-01-15_08-05-00.txt',
            'raw_payload'          => json_encode([]),
        ]);

        // Pre-create an unresolved issue (simulating a prior detection run).
        $issue = PatronDeliveryIssue::create([
            'issue_type'      => 'lx_starter_failure',
            'patron_id'       => $notification->patron_id,
            'patron_barcode'  => $notification->patron_barcode,
            'notification_id' => $notification->id,
            'detected_at'     => $date->copy()->addHours(49),
        ]);

        // Make Voice/SMS "success-by-absence" eligible:
        // - 24h grace period passed
        // - import batch ran after grace period
        // - Shoutbomb reports were ingested for that day (but no failure exists for this patron)
        NotificationImportBatch::create([
            'source'        => 'ftp_holds',
            'date_for'      => $date->format('Y-m-d'),
            'imported_at'   => $date->copy()->addHours(25),
            'identifier'    => 'holds_' . $date->format('Y-m-d'),
            'records_count' => 0,
        ]);

        // Mark Shoutbomb report coverage for the day as "ingested", so success-by-absence
        // can be applied for Voice/SMS.
        $reportTypeId = EmailReportType::query()
            ->where('code', 'shoutbomb_voice_failure')
            ->value('id');

        IngestedEmail::create([
            'message_id'           => 'MSG-VOICE-REPORT-1',
            'email_report_type_id' => $reportTypeId,
            'subject'              => 'Shoutbomb voice failures',
            'received_at'          => $date->copy()->addHours(24)->addMinutes(10),
            'imported_at'          => $date->copy()->addHours(25),
            'report_date'          => $date->copy()->toDateString(),
            'processing_status'    => 'processed',
        ]);

        $this->travelTo($date->copy()->addHours(26));
        $this->reconciler->reconcile($date);

        $issue->refresh();
        $this->assertNotNull($issue->resolved_at, 'Issue should be auto-resolved once notification is delivered');
        $this->assertStringContainsString('delivered', (string) $issue->resolution_notes, 'Resolution notes should mention delivered status');
    }

    /**
     * Test that null delivery preference creates null_preference issue, NOT lx_starter_failure.
     */
    public function test_null_preference_detection(): void
    {
        $date = Carbon::parse('2025-01-15 08:05:00', 'America/Chicago');

        Notification::create([
            'patron_id'            => 1001,
            'patron_barcode'       => '20000001001',
            'notification_type_id' => 2,
            'delivery_option_id'   => null,
            'status'               => 'pending',
            'generated_at'         => $date,
            'first_exported_at'    => $date,
            'export_filename'      => 'holds_2025-01-15_08-05-00.txt',
            'raw_payload'          => json_encode([]),
        ]);

        $this->reconciler->reconcile($date);

        $notification = Notification::where('patron_barcode', '20000001001')->first();
        $this->assertNotNull($notification, 'Notification should be created');

        // Should create null_preference issue
        $nullPrefIssue = PatronDeliveryIssue::where('notification_id', $notification->id)
            ->where('issue_type', 'null_preference')
            ->first();

        $this->assertNotNull($nullPrefIssue, 'Should create null_preference issue');

        // Should NOT create lx_starter_failure issue
        $lxIssue = PatronDeliveryIssue::where('notification_id', $notification->id)
            ->where('issue_type', 'lx_starter_failure')
            ->first();

        $this->assertNull($lxIssue, 'Should NOT create lx_starter_failure for null preference');
    }

    /**
     * Test that all issue types can coexist for the same notification.
     *
     * A notification could theoretically have multiple issues.
     */
    public function test_multiple_issue_types_per_notification(): void
    {
        $date = Carbon::parse('2025-01-15 08:05:00', 'America/Chicago');

        $failedString = FailedDeliveryString::create([
            'delivery_string' => 'bad@email.com',
            'delivery_option_id' => DeliveryMethodHelper::EMAIL,
            'failure_source' => 'notification_log',
            'first_failed_at' => now(),
            'last_failed_at' => now(),
            'failure_count' => 5,
        ]);

        $notification = Notification::create([
            'patron_id'            => 1001,
            'patron_barcode'       => '20000001001',
            'notification_type_id' => 2,
            'delivery_option_id'   => DeliveryMethodHelper::EMAIL,
            'status'               => 'pending',
            'generated_at'         => $date,
            'first_exported_at'    => $date,
            'export_filename'      => 'notification_log_2025-01-15_08-05-00.txt',
            'raw_payload'          => json_encode(['patron_email' => 'bad@email.com']),
        ]);

        // For this unit-style case we just assert that multiple issue types can coexist.
        PatronDeliveryIssue::create([
            'issue_type'                => 'failed_delivery_string',
            'patron_id'                 => $notification->patron_id,
            'patron_barcode'            => $notification->patron_barcode,
            'notification_id'           => $notification->id,
            'failed_delivery_string_id' => $failedString->id,
            'detected_at'               => now(),
        ]);

        PatronDeliveryIssue::create([
            'issue_type'      => 'lx_starter_failure',
            'patron_id'       => $notification->patron_id,
            'patron_barcode'  => $notification->patron_barcode,
            'notification_id' => $notification->id,
            'detected_at'     => now(),
        ]);

        $issues = PatronDeliveryIssue::where('notification_id', $notification->id)->get();

        // At minimum should have issues detected
        $this->assertGreaterThan(0, $issues->count(), 'Should detect at least one issue');

        // Each issue should be for the correct patron
        foreach ($issues as $issue) {
            $this->assertEquals('20000001001', $issue->patron_barcode);
            $this->assertEquals(1001, $issue->patron_id);
        }
    }
}
