<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\UiMessage;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class NotificationsPatronFailureLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable auth middleware so routes under the `auth` group are accessible in tests.
        $this->withoutMiddleware(Authenticate::class);
    }

    #[Test]
    public function patron_view_uses_sms_opt_out_label_for_opt_out_failure(): void
    {
        $notification = Notification::factory()->create([
            'patron_barcode'     => '20000001001',
            'status'             => 'failed',
            'delivery_option_id' => 8,
            'failure_reason'     => 'Patron opted out of SMS',
        ]);

        $response = $this->get(route('notices.patron', $notification->patron_barcode));

        $response->assertStatus(200);
        // Default label comes from UiMessage seeding (ucwords of key): "Sms Opt Out".
        $response->assertSee('Sms Opt Out', false);
    }

    #[Test]
    public function patron_view_uses_ui_message_override_for_email_bounce(): void
    {
        $notification = Notification::factory()->create([
            'patron_barcode'     => '20000001002',
            'status'             => 'failed',
            'delivery_option_id' => 2,
            'failure_reason'     => 'Email bounced permanently',
        ]);

        config()->set('notices.ui_labels.email_bounce', 'Email bounced');

        UiMessage::updateOrCreate(
            ['key' => 'email_bounce'],
            [
                'label'   => 'Custom bounce label',
                'message' => '',
            ]
        );

        $response = $this->get(route('notices.patron', $notification->patron_barcode));

        $response->assertStatus(200);
        $response->assertSee('Custom bounce label', false);
    }

    #[Test]
    public function patron_view_falls_back_to_failed_when_no_ui_failure_key(): void
    {
        $notification = Notification::factory()->create([
            'patron_barcode'     => '20000001003',
            'status'             => 'failed',
            'delivery_option_id' => 1,
            'failure_reason'     => 'Printer jam',
        ]);

        $response = $this->get(route('notices.patron', $notification->patron_barcode));

        $response->assertStatus(200);
        $response->assertSee('Failed', false);
    }
}
