<?php

namespace Dcplibrary\Notices\Tests\Unit\LaravelUpgrade;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class RoutesAndControllersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_verifies_all_notification_routes_are_accessible()
    {
        $routes = [
            'notices.api.logs.index' => 'get',
            'notices.api.logs.stats' => 'get',
        ];

        foreach ($routes as $routeName => $method) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} should be registered");
            $this->assertContains(strtoupper($method), $route->methods());
        }
    }

    /** @test */
    public function it_verifies_notification_index_endpoint_works()
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
    public function it_verifies_notification_show_endpoint_works()
    {
        $notification = NotificationLog::factory()->create();

        $response = $this->getJson(route('notices.api.logs.show', $notification));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'patron_id', 'notification_date']
            ]);
    }

    /** @test */
    public function it_verifies_notification_stats_endpoint_works()
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
    public function it_verifies_summary_routes_are_accessible()
    {
        $routes = [
            'notifications.api.summaries.index',
            'notifications.api.summaries.totals',
            'notifications.api.summaries.by-type',
            'notifications.api.summaries.by-delivery',
        ];

        foreach ($routes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} should be registered");
        }
    }

    /** @test */
    public function it_verifies_analytics_routes_are_accessible()
    {
        $routes = [
            'notifications.api.analytics.overview',
            'notifications.api.analytics.time-series',
            'notifications.api.analytics.top-patrons',
            'notifications.api.analytics.success-rate-trend',
        ];

        foreach ($routes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} should be registered");
        }
    }

    /** @test */
    public function it_verifies_shoutbomb_routes_are_accessible()
    {
        $routes = [
            'notifications.api.shoutbomb.deliveries',
            'notifications.api.shoutbomb.deliveries.stats',
            'notifications.api.shoutbomb.keyword-usage',
            'notifications.api.shoutbomb.keyword-usage.summary',
            'notifications.api.shoutbomb.registrations',
            'notifications.api.shoutbomb.registrations.latest',
        ];

        foreach ($routes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} should be registered");
        }
    }

    /** @test */
    public function it_verifies_route_parameter_binding_works()
    {
        $notification = NotificationLog::factory()->create();

        $response = $this->getJson(
            route('notices.api.logs.show', ['notification' => $notification->id])
        );

        $response->assertStatus(200);
        $this->assertEquals($notification->id, $response->json('data.id'));
    }

    /** @test */
    public function it_verifies_route_parameter_binding_handles_not_found()
    {
        $response = $this->getJson(
            route('notices.api.logs.show', ['notification' => 99999])
        );

        $response->assertStatus(404);
    }

    /** @test */
    public function it_verifies_controllers_handle_query_parameters()
    {
        NotificationLog::factory()->count(5)->create(['notification_type_id' => 4]);
        NotificationLog::factory()->count(3)->create(['notification_type_id' => 5]);

        $response = $this->getJson(
            route('notices.api.logs.index', ['type_id' => 4])
        );

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function it_verifies_controllers_handle_pagination()
    {
        NotificationLog::factory()->count(50)->create();

        $response = $this->getJson(
            route('notices.api.logs.index', ['per_page' => 10])
        );

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(50, $response->json('meta.total'));
    }

    /** @test */
    public function it_verifies_controllers_return_proper_json_structure()
    {
        $notification = NotificationLog::factory()->create();

        $response = $this->getJson(route('notices.api.logs.show', $notification));

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
        $this->assertArrayHasKey('id', $response->json('data'));
    }

    /** @test */
    public function it_verifies_controllers_handle_date_range_filtering()
    {
        NotificationLog::factory()->create([
            'notification_date' => now()->subDays(10),
        ]);
        NotificationLog::factory()->create([
            'notification_date' => now()->subDays(5),
        ]);
        NotificationLog::factory()->create([
            'notification_date' => now()->subDays(2),
        ]);

        $response = $this->getJson(
            route('notices.api.logs.index', [
                'start_date' => now()->subDays(6)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ])
        );

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_verifies_controllers_handle_sorting()
    {
        NotificationLog::factory()->create(['patron_id' => 100]);
        NotificationLog::factory()->create(['patron_id' => 300]);
        NotificationLog::factory()->create(['patron_id' => 200]);

        $response = $this->getJson(
            route('notices.api.logs.index', [
                'sort_by' => 'patron_id',
                'sort_dir' => 'asc',
            ])
        );

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(100, $data[0]['patron_id']);
        $this->assertEquals(200, $data[1]['patron_id']);
        $this->assertEquals(300, $data[2]['patron_id']);
    }

    /** @test */
    public function it_verifies_route_prefixes_are_correct()
    {
        $notificationRoute = Route::getRoutes()->getByName('notices.api.logs.index');
        
        $this->assertNotNull($notificationRoute);
        $this->assertStringContainsString('notifications', $notificationRoute->uri());
    }

    /** @test */
    public function it_verifies_controllers_use_resource_transformers()
    {
        $notification = NotificationLog::factory()->create();

        $response = $this->getJson(route('notices.api.logs.show', $notification));

        $response->assertStatus(200);
        
        // Verify that the response is transformed (wrapped in 'data' key)
        $this->assertArrayHasKey('data', $response->json());
    }
}
