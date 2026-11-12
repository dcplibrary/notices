<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Services\NotificationReconciler;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Email/Mail NotificationLog rebuilds must be day-scoped and must not reset
 * statuses reconcile has already finalized.
 */
class EmailLogRebuildScopeTest extends TestCase
{
    use RefreshDatabase;

    private function logRow(int $patronId, string $barcode, string $noticeDate): void
    {
        DeliveryConfirmation::create([
            'patron_id'              => $patronId,
            'patron_barcode'         => $barcode,
            'notification_type_id'   => 6,
            'delivery_option_id'     => 2,
            'notice_date'            => $noticeDate,
            'delivery_string'        => "p{$patronId}@example.com",
            'notification_status_id' => 12,
            'confirmation_source'    => 'notification_log',
            'source_filename'        => 'notification_log_' . substr($noticeDate, 0, 10) . '_05-00-01.txt',
        ]);
    }

    private function notificationFor(int $patronId, string $noticeDate): ?Notification
    {
        return Notification::query()
            ->where('notification_key', NotificationReconciler::emailMailNotificationKey($patronId, 2, 6, $noticeDate))
            ->first();
    }

    public function test_creates_separate_day_scoped_notifications_and_ignores_out_of_window_log_rows(): void
    {
        $this->logRow(1001, '20000000001001', '2026-01-10 08:00:00');
        $this->logRow(1001, '20000000001001', '2026-03-01 08:00:00');
        $this->logRow(1001, '20000000001001', '2026-03-01 08:05:00');
        $this->logRow(1002, '20000000001002', '2026-01-10 08:00:00');

        app(NotificationReconciler::class)->deduplicate(Carbon::parse('2026-03-02'));

        $march = $this->notificationFor(1001, '2026-03-01');
        $this->assertNotNull($march);
        $this->assertCount(2, json_decode($march->raw_payload, true));
        $this->assertSame('2026-03-01', $march->first_exported_at->toDateString());
        $this->assertSame('2026-03-01', $march->last_exported_at->toDateString());
        $this->assertSame('pending', $march->status);

        $this->assertNull($this->notificationFor(1001, '2026-01-10'));
        $this->assertNull($this->notificationFor(1002, '2026-01-10'));
        $this->assertNull(
            Notification::query()
                ->where('notification_key', NotificationReconciler::legacyEmailMailNotificationKey(1001, 2, 6))
                ->first()
        );
    }

    public function test_rebuild_does_not_reset_reconciled_status(): void
    {
        $this->logRow(3001, '20000000003001', '2026-03-01 08:00:00');

        app(NotificationReconciler::class)->deduplicate(Carbon::parse('2026-03-02'));

        $notification = $this->notificationFor(3001, '2026-03-01');
        $this->assertNotNull($notification);

        $notification->update([
            'status'         => 'failed',
            'failure_reason' => 'Email Failed',
            'failed_at'      => '2026-03-01 14:00:00',
        ]);

        app(NotificationReconciler::class)->deduplicate(Carbon::parse('2026-03-02'));

        $notification->refresh();
        $this->assertSame('failed', $notification->status);
        $this->assertSame('Email Failed', $notification->failure_reason);
        $this->assertSame('2026-03-01 14:00:00', $notification->failed_at->format('Y-m-d H:i:s'));
    }
}
