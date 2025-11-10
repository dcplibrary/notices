<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 * Tests for API routes to ensure all routes are properly registered.
 */
class ApiRoutesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_verifies_all_api_routes_are_registered()
    {
        $expectedRoutes = [
            // Logs (formerly notifications)
            'notices.api.logs.index',
            'notices.api.logs.stats',
            'notices.api.logs.show',
            // Summaries
            'notices.api.summaries.index',
            'notices.api.summaries.totals',
            'notices.api.summaries.by-type',
            'notices.api.summaries.by-delivery',
            'notices.api.summaries.show',
            // Analytics
            'notices.api.analytics.overview',
            'notices.api.analytics.time-series',
            'notices.api.analytics.top-patrons',
            'notices.api.analytics.success-rate-trend',
            // Shoutbomb
            'notices.api.shoutbomb.deliveries',
            'notices.api.shoutbomb.deliveries.stats',
            'notices.api.shoutbomb.keyword-usage',
            'notices.api.shoutbomb.keyword-usage.summary',
            'notices.api.shoutbomb.registrations',
            'notices.api.shoutbomb.registrations.latest',
            // Verification
            'notices.api.verification.verify',
            'notices.api.verification.search',
            'notices.api.verification.patron',
            'notices.api.verification.failures',
            'notices.api.verification.timeline',
            'notices.api.verification.troubleshooting.summary',
            'notices.api.verification.troubleshooting.by-reason',
            'notices.api.verification.troubleshooting.by-type',
            'notices.api.verification.troubleshooting.mismatches',
        ];

        foreach ($expectedRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "API route {$routeName} should be registered");
        }
    }

    /** @test */
    public function logs_index_route_is_accessible()
    {
        NotificationLog::factory()->count(5)->create();

        $response = $this->getJson(route('notices.api.logs.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    /** @test */
    public function logs_show_route_is_accessible()
    {
        $notification = NotificationLog::factory()->create();

        $response = $this->getJson(route('notices.api.logs.show', $notification));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'patron_id',
                    'notification_date',
                ]
            ]);
    }

    /** @test */
    public function logs_stats_route_is_accessible()
    {
        NotificationLog::factory()->count(10)->create();

        $response = $this->getJson(route('notices.api.logs.stats'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'successful',
                'failed',
                'success_rate',
                'failure_rate',
            ]);
    }

    /** @test */
    public function summaries_index_route_is_accessible()
    {
        DailyNotificationSummary::factory()->count(5)->create();

        $response = $this->getJson(route('notices.api.summaries.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    /** @test */
    public function summaries_totals_route_is_accessible()
    {
        DailyNotificationSummary::factory()->count(5)->create();

        $response = $this->getJson(route('notices.api.summaries.totals'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_sent',
                'total_success',
                'total_failed',
                'success_rate',
            ]);
    }

    /** @test */
    public function analytics_overview_route_is_accessible()
    {
        $response = $this->getJson(route('notices.api.analytics.overview'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_sent',
                'success_rate',
            ]);
    }

    /** @test */
    public function shoutbomb_deliveries_route_is_accessible()
    {
        $response = $this->getJson(route('notices.api.shoutbomb.deliveries'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    /** @test */
    public function verification_search_route_is_accessible()
    {
        $response = $this->getJson(route('notices.api.verification.search', [
            'patron_barcode' => 'TEST123'
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    /** @test */
    public function verification_patron_route_is_accessible()
    {
        $response = $this->getJson(route('notices.api.verification.patron', [
            'barcode' => 'TEST123'
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'statistics',
            ]);
    }

    /** @test */
    public function all_api_routes_use_correct_http_methods()
    {
        $getRoutes = [
            'notices.api.logs.index',
            'notices.api.logs.show',
            'notices.api.logs.stats',
            'notices.api.summaries.index',
            'notices.api.summaries.totals',
            'notices.api.analytics.overview',
            'notices.api.shoutbomb.deliveries',
            'notices.api.verification.search',
            'notices.api.verification.patron',
        ];

        foreach ($getRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} should exist");
            $this->assertContains('GET', $route->methods());
        }
    }

    /** @test */
    public function api_routes_return_json_responses()
    {
        NotificationLog::factory()->create();

        $apiRoutes = [
            'notices.api.logs.index',
            'notices.api.logs.stats',
            'notices.api.summaries.index',
            'notices.api.summaries.totals',
            'notices.api.analytics.overview',
        ];

        foreach ($apiRoutes as $routeName) {
            $response = $this->getJson(route($routeName));
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/json');
        }
    }

    /** @test */
    public function api_route_name_prefixes_are_consistent()
    {
        $routes = Route::getRoutes()->getRoutes();
        $apiRoutes = array_filter($routes, function ($route) {
            return $route->getName() && str_starts_with($route->getName(), 'notices.api.');
        });

        $this->assertNotEmpty($apiRoutes, 'Should have routes with notices.api. prefix');

        foreach ($apiRoutes as $route) {
            $this->assertStringStartsWith('notices.api.', $route->getName());
        }
    }

    /** @test */
    public function verification_troubleshooting_routes_are_accessible()
    {
        $troubleshootingRoutes = [
            'notices.api.verification.troubleshooting.summary',
            'notices.api.verification.troubleshooting.by-reason',
            'notices.api.verification.troubleshooting.by-type',
            'notices.api.verification.troubleshooting.mismatches',
        ];

        foreach ($troubleshootingRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Troubleshooting route {$routeName} should exist");

            $response = $this->getJson(route($routeName));
            $response->assertStatus(200);
        }
    }

    /** @test */
    public function api_routes_accept_query_parameters()
    {
        NotificationLog::factory()->count(5)->create([
            'notification_type_id' => 4
        ]);

        // Test filtering by type
        $response = $this->getJson(route('notices.api.logs.index', [
            'type_id' => 4
        ]));
        $response->assertStatus(200);

        // Test date range filtering
        $response = $this->getJson(route('notices.api.logs.index', [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $response->assertStatus(200);

        // Test pagination
        $response = $this->getJson(route('notices.api.logs.index', [
            'per_page' => 10
        ]));
        $response->assertStatus(200);
    }

    /** @test */
    public function verification_timeline_route_requires_id_parameter()
    {
        $notification = NotificationLog::factory()->create();

        $response = $this->getJson(route('notices.api.verification.timeline', [
            'id' => $notification->id
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'notice',
                'verification',
            ]);
    }
}
