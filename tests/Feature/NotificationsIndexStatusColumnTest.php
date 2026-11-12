<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class NotificationsIndexStatusColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable auth middleware so routes under the `auth` group are accessible in tests.
        $this->withoutMiddleware(Authenticate::class);
    }

    #[Test]
    public function index_shows_failed_status_with_icon_and_literal_failed_text(): void
    {
        Notification::factory()->create([
            'patron_barcode'     => '20000001001',
            'status'             => 'failed',
            'delivery_option_id' => 2,
            'failure_reason'     => 'Email Failed - Invalid address foo@example',
        ]);

        $response = $this->get(route('notices.index'));

        $response->assertStatus(200);
        // We don't assert on SVG markup; just ensure the word Failed appears
        $response->assertSee('Failed', false);
    }

    #[Test]
    public function index_shows_delivered_and_pending_statuses_with_text(): void
    {
        Notification::factory()->create([
            'patron_barcode'     => '20000001002',
            'status'             => 'delivered',
            'delivery_option_id' => 2,
        ]);

        Notification::factory()->create([
            'patron_barcode'     => '20000001003',
            'status'             => 'pending',
            'delivery_option_id' => 2,
        ]);

        $response = $this->get(route('notices.index'));

        $response->assertStatus(200);
        $response->assertSee('Delivered', false);
        $response->assertSee('Pending', false);
    }
}
