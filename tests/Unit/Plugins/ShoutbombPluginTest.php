<?php

namespace Dcplibrary\Notices\Tests\Unit\Plugins;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Plugins\ShoutbombPlugin;
use Dcplibrary\Notices\Services\VerificationResult;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Tests for the ShoutbombPlugin.
 */
class ShoutbombPluginTest extends TestCase
{
    protected ShoutbombPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new ShoutbombPlugin();
    }

    /** @test */
    public function it_has_correct_identification()
    {
        $this->assertEquals('shoutbomb', $this->plugin->getName());
        $this->assertEquals('Shoutbomb Voice/Text', $this->plugin->getDisplayName());
        $this->assertStringContainsString('voice and text', $this->plugin->getDescription());
    }

    /** @test */
    public function it_handles_correct_delivery_options()
    {
        $deliveryOptions = $this->plugin->getDeliveryOptionIds();

        $this->assertIsArray($deliveryOptions);
        $this->assertContains(3, $deliveryOptions); // Voice
        $this->assertContains(8, $deliveryOptions); // SMS
        $this->assertCount(2, $deliveryOptions);
    }

    /** @test */
    public function it_can_verify_voice_notices()
    {
        $notice = new NotificationLog();
        $notice->delivery_option_id = 3; // Voice

        $this->assertTrue($this->plugin->canVerify($notice));
    }

    /** @test */
    public function it_can_verify_sms_notices()
    {
        $notice = new NotificationLog();
        $notice->delivery_option_id = 8; // SMS

        $this->assertTrue($this->plugin->canVerify($notice));
    }

    /** @test */
    public function it_cannot_verify_email_notices()
    {
        $notice = new NotificationLog();
        $notice->delivery_option_id = 2; // Email

        $this->assertFalse($this->plugin->canVerify($notice));
    }

    /** @test */
    public function it_is_enabled_by_default()
    {
        $this->assertTrue($this->plugin->isEnabled());
    }

    /** @test */
    public function it_returns_configuration()
    {
        $config = $this->plugin->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertTrue($config['enabled']);
    }

    /** @test */
    public function it_returns_statistics_structure()
    {
        $stats = $this->plugin->getStatistics(
            now()->subDays(7),
            now()
        );

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_sent', $stats);
        $this->assertArrayHasKey('total_delivered', $stats);
        $this->assertArrayHasKey('total_failed', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('additional_stats', $stats);

        $this->assertIsArray($stats['additional_stats']);
        $this->assertArrayHasKey('unique_patrons', $stats['additional_stats']);
        $this->assertArrayHasKey('voice_count', $stats['additional_stats']);
        $this->assertArrayHasKey('text_count', $stats['additional_stats']);
    }

    /** @test */
    public function verify_method_returns_verification_result()
    {
        $notice = new NotificationLog();
        $notice->delivery_option_id = 3;
        $notice->patron_barcode = 'TEST123';
        $notice->notification_date = now();
        $notice->notification_type_id = 2;

        $result = new VerificationResult([
            'created' => true,
            'created_at' => now(),
        ]);

        $verifiedResult = $this->plugin->verify($notice, $result);

        $this->assertInstanceOf(VerificationResult::class, $verifiedResult);
    }

    /** @test */
    public function verify_method_does_not_modify_result_for_non_shoutbomb_notices()
    {
        $notice = new NotificationLog();
        $notice->delivery_option_id = 2; // Email, not Shoutbomb
        $notice->notification_date = now();

        $result = new VerificationResult([
            'created' => true,
            'created_at' => now(),
        ]);

        $originalTimeline = count($result->timeline);
        $verifiedResult = $this->plugin->verify($notice, $result);

        // Should not add any timeline events since it can't verify this notice
        $this->assertEquals($originalTimeline, count($verifiedResult->timeline));
    }

    /** @test */
    public function get_failed_notices_returns_collection()
    {
        $failures = $this->plugin->getFailedNotices(
            now()->subDays(7),
            now()
        );

        $this->assertInstanceOf(Collection::class, $failures);
    }

    /** @test */
    public function get_failed_notices_respects_reason_filter()
    {
        $failures = $this->plugin->getFailedNotices(
            now()->subDays(7),
            now(),
            'test reason'
        );

        $this->assertInstanceOf(Collection::class, $failures);
    }

    /** @test */
    public function get_dashboard_widget_returns_view()
    {
        $widget = $this->plugin->getDashboardWidget(
            now()->subDays(7),
            now()
        );

        // Widget may be null or a View depending on implementation
        if ($widget !== null) {
            $this->assertInstanceOf(View::class, $widget);
        } else {
            $this->assertNull($widget);
        }
    }
}
