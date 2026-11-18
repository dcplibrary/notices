<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationActivityLog;
use Dcplibrary\Notices\Services\ActivityLogArchiver;
use Dcplibrary\Notices\Services\NoticesDataBackup;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

class DataBackupTest extends TestCase
{
    #[Test]
    public function aDataBackupOmitsActivityLogsAndRestoresNotifications(): void
    {
        $notification = Notification::create([
            'patron_id'          => 4242,
            'patron_barcode'     => '20000004242',
            'notification_type'  => 'Hold',
            'delivery_option_id' => 2,
            'status'             => 'pending',
            'is_combined'        => false,
        ]);

        NotificationActivityLog::create([
            'notification_id' => $notification->id,
            'occurred_at'     => now()->subDays(120),
            'source'          => 'system',
            'description'     => 'old activity that must stay out of the backup',
        ]);

        $path = storage_path('app/notices-data-backup-test.json');
        app(NoticesDataBackup::class)->writeData($path, 'test snapshot');

        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('20000004242', $contents);
        $this->assertStringContainsString('notification_activity_logs', explode("\n", $contents, 2)[0]);
        $this->assertStringNotContainsString('old activity that must stay out of the backup', $contents);

        $notification->delete();
        $this->assertSame(0, Notification::query()->count());

        $header = app(NoticesDataBackup::class)->readHeader($path);
        $this->assertSame('notices-data-backup', $header['type']);
        app(NoticesDataBackup::class)->restoreDataTables($path, $header);

        $this->assertSame(1, Notification::query()->where('patron_barcode', '20000004242')->count());
        $this->assertSame(1, NotificationActivityLog::query()->count());

        @unlink($path);
    }

    #[Test]
    public function archivingRemovesOnlyLogsOlderThanTheRetentionWindow(): void
    {
        config()->set('notices.activity_log.retention_days', 90);

        NotificationActivityLog::create([
            'notification_id' => 1,
            'occurred_at'     => now()->subDays(120),
            'source'          => 'system',
            'description'     => 'archive me',
        ]);
        NotificationActivityLog::create([
            'notification_id' => 1,
            'occurred_at'     => now()->subDay(),
            'source'          => 'system',
            'description'     => 'keep me',
        ]);

        $dry = Artisan::call('notices:archive-activity-logs', ['--dry-run' => true]);
        $this->assertSame(0, $dry);
        $this->assertSame(2, NotificationActivityLog::query()->count());

        $result = app(ActivityLogArchiver::class)->archive(90, false);

        $this->assertSame(1, $result['archived']);
        $this->assertFileExists($result['path']);
        $this->assertSame('keep me', NotificationActivityLog::query()->value('description'));

        $archived = gzfile($result['path']);
        $this->assertIsArray($archived);
        $this->assertStringContainsString('archive me', implode('', $archived));

        @unlink($result['path']);
    }
}
