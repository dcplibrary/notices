<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationActivityLog;
use Dcplibrary\Notices\Models\NoticesSetting;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Services\ActivityLogRetentionSettings;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

class ActivityLogRetentionSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(Authenticate::class);
    }

    #[Test]
    public function cleanupPageShowsTheDefaultRetention(): void
    {
        $response = $this->get(route('notices.settings.maintenance.cleanup'));

        $response->assertOk();
        $response->assertSee('Activity log retention');
        $response->assertSee('Keep unresolved failures');
        $response->assertSee('value="90"', false);
        $response->assertDontSee('checked', false);

        $this->get(route('notices.settings.index'))
            ->assertOk()
            ->assertSee('Keeps 90 days of activity logs')
        ;
    }

    #[Test]
    public function savingRetentionKeepsTheEditedDaysAndTheUnresolvedChoice(): void
    {
        $response = $this->post(route('notices.settings.maintenance.activity-log-retention'), [
            'activity_log_days' => 120,
            'keep_unresolved'   => '1',
        ]);

        $response->assertRedirect(route('notices.settings.maintenance.cleanup'));

        $settings = app(ActivityLogRetentionSettings::class);
        $this->assertSame(120, $settings->days());
        $this->assertTrue($settings->keepUnresolved());

        $this->get(route('notices.settings.maintenance.cleanup'))
            ->assertSee('value="120"', false)
            ->assertSee('checked', false)
        ;
    }

    #[Test]
    public function runningTheArchiveUsesTheFormAndLeavesOpenFailures(): void
    {
        $open = Notification::create([
            'patron_id'          => 6101,
            'patron_barcode'     => '20000006101',
            'notification_type'  => 'Hold',
            'delivery_option_id' => 2,
            'status'             => 'failed',
            'is_combined'        => false,
        ]);
        $closed = Notification::create([
            'patron_id'          => 6102,
            'patron_barcode'     => '20000006102',
            'notification_type'  => 'Hold',
            'delivery_option_id' => 2,
            'status'             => 'failed',
            'is_combined'        => false,
        ]);

        PatronDeliveryIssue::create([
            'issue_type'      => 'failed_delivery_string',
            'notification_id' => $open->id,
            'detected_at'     => now()->subDays(120),
        ]);
        PatronDeliveryIssue::create([
            'issue_type'      => 'failed_delivery_string',
            'notification_id' => $closed->id,
            'detected_at'     => now()->subDays(120),
            'resolved_at'     => now()->subDays(10),
        ]);

        NotificationActivityLog::create([
            'notification_id' => $open->id,
            'occurred_at'     => now()->subDays(120),
            'source'          => 'system',
            'description'     => 'still open',
        ]);
        NotificationActivityLog::create([
            'notification_id' => $closed->id,
            'occurred_at'     => now()->subDays(120),
            'source'          => 'system',
            'description'     => 'already resolved',
        ]);

        $this->post(route('notices.settings.maintenance.activity-log-archive'), [
            'activity_log_days' => 90,
            'keep_unresolved'   => '1',
        ])->assertRedirect(route('notices.settings.maintenance.cleanup'));

        $this->assertSame(['still open'], NotificationActivityLog::query()->pluck('description')->all());
        $this->assertSame('90', NoticesSetting::query()->where('key', ActivityLogRetentionSettings::DAYS_KEY)->value('value'));
        $this->assertSame('1', NoticesSetting::query()->where('key', ActivityLogRetentionSettings::KEEP_UNRESOLVED_KEY)->value('value'));
    }

    #[Test]
    public function theScheduledCommandUsesTheSavedKeepUnresolvedSetting(): void
    {
        app(ActivityLogRetentionSettings::class)->save(90, true);

        $open = Notification::create([
            'patron_id'          => 6103,
            'patron_barcode'     => '20000006103',
            'notification_type'  => 'Hold',
            'delivery_option_id' => 2,
            'status'             => 'failed',
            'is_combined'        => false,
        ]);
        PatronDeliveryIssue::create([
            'issue_type'      => 'failed_delivery_string',
            'notification_id' => $open->id,
            'detected_at'     => now()->subDays(120),
        ]);
        NotificationActivityLog::create([
            'notification_id' => $open->id,
            'occurred_at'     => now()->subDays(120),
            'source'          => 'system',
            'description'     => 'kept by the nightly setting',
        ]);

        Artisan::call('notices:archive-activity-logs', ['--force' => true]);

        $this->assertSame(
            ['kept by the nightly setting'],
            NotificationActivityLog::query()->pluck('description')->all(),
        );
    }
}
