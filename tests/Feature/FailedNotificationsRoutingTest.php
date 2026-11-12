<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Middleware\Authenticate;
use PHPUnit\Framework\Attributes\Test;

class FailedNotificationsRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable auth middleware so routes under the `auth` group are accessible in tests.
        $this->withoutMiddleware(Authenticate::class);
    }

    #[Test]
    public function dashboard_failed_deliveries_card_links_to_failed_notifications_list(): void
    {
        $response = $this->get(route('notices.dashboard'));

        $response->assertStatus(200);

        // Expect a link to the notifications list filtered by failed status
        $response->assertSee('/notices/list', false);
        $response->assertSee('status=failed', false);
    }

    #[Test]
    public function issues_failure_charts_link_to_failed_notifications_list(): void
    {
        $response = $this->get(route('notices.issues'));

        $response->assertStatus(200);

        // The issues view uses route('notices.index', array_merge($baseQuery, ['status' => 'failed']))
        $baseUrl = route('notices.index');

        // We just assert that links containing the base index URL and 'status=failed' are present.
        $response->assertSee($baseUrl, false);
        $response->assertSee('status=failed', false);
    }
}
