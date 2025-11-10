<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\NotificationSetting;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 * Tests for web routes to ensure all routes are properly registered.
 */
class WebRoutesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_verifies_all_web_routes_are_registered()
    {
        $expectedRoutes = [
            'notices.dashboard',
            'notices.list',
            'notices.analytics',
            'notices.shoutbomb',
            'notices.troubleshooting',
            'notices.troubleshooting.export',
            'notices.verification.index',
            'notices.verification.export',
            'notices.verification.patron',
            'notices.verification.patron.export',
            'notices.verification.timeline',
            'notices.settings.index',
            'notices.settings.scoped',
            'notices.settings.store',
            'notices.settings.show',
            'notices.settings.edit',
            'notices.settings.update',
            'notices.settings.destroy',
        ];

        foreach ($expectedRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} should be registered");
        }
    }

    /** @test */
    public function dashboard_route_is_accessible()
    {
        $response = $this->get(route('notices.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('notices::dashboard.index');
    }

    /** @test */
    public function notifications_list_route_is_accessible()
    {
        $response = $this->get(route('notices.list'));

        $response->assertStatus(200);
        $response->assertViewIs('notices::dashboard.notifications');
    }

    /** @test */
    public function analytics_route_is_accessible()
    {
        $response = $this->get(route('notices.analytics'));

        $response->assertStatus(200);
        $response->assertViewIs('notices::dashboard.analytics');
    }

    /** @test */
    public function shoutbomb_route_is_accessible()
    {
        $response = $this->get(route('notices.shoutbomb'));

        $response->assertStatus(200);
        $response->assertViewIs('notices::dashboard.shoutbomb');
    }

    /** @test */
    public function troubleshooting_route_is_accessible()
    {
        $response = $this->get(route('notices.troubleshooting'));

        $response->assertStatus(200);
        $response->assertViewIs('notices::dashboard.troubleshooting');
    }

    /** @test */
    public function verification_index_route_is_accessible()
    {
        $response = $this->get(route('notices.verification.index'));

        $response->assertStatus(200);
        $response->assertViewIs('notices::dashboard.verification');
    }

    /** @test */
    public function verification_timeline_route_is_accessible()
    {
        $notice = NotificationLog::factory()->create();

        $response = $this->get(route('notices.verification.timeline', $notice->id));

        $response->assertStatus(200);
        $response->assertViewIs('notices::dashboard.verification-timeline');
    }

    /** @test */
    public function verification_patron_route_is_accessible()
    {
        $response = $this->get(route('notices.verification.patron', ['barcode' => 'TEST123']));

        $response->assertStatus(200);
        $response->assertViewIs('notices::dashboard.verification-patron');
    }

    /** @test */
    public function settings_index_route_is_accessible()
    {
        $response = $this->get(route('notices.settings.index'));

        $response->assertStatus(200);
        $response->assertViewIs('notices::settings.index');
    }

    /** @test */
    public function settings_show_route_is_accessible()
    {
        $setting = NotificationSetting::factory()->create();

        $response = $this->get(route('notices.settings.show', $setting->id));

        $response->assertStatus(200);
        $response->assertViewIs('notices::settings.show');
    }

    /** @test */
    public function settings_edit_route_is_accessible()
    {
        $setting = NotificationSetting::factory()->create(['is_editable' => true]);

        $response = $this->get(route('notices.settings.edit', $setting->id));

        $response->assertStatus(200);
        $response->assertViewIs('notices::settings.edit');
    }

    /** @test */
    public function export_routes_have_correct_http_methods()
    {
        $exportRoutes = [
            'notices.verification.export' => 'GET',
            'notices.verification.patron.export' => 'GET',
            'notices.troubleshooting.export' => 'GET',
        ];

        foreach ($exportRoutes as $routeName => $method) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Export route {$routeName} should exist");
            $this->assertContains($method, $route->methods());
        }
    }

    /** @test */
    public function settings_routes_have_correct_http_methods()
    {
        $settingsRoutes = [
            'notices.settings.index' => 'GET',
            'notices.settings.show' => 'GET',
            'notices.settings.edit' => 'GET',
            'notices.settings.store' => 'POST',
            'notices.settings.update' => 'PUT',
            'notices.settings.destroy' => 'DELETE',
        ];

        foreach ($settingsRoutes as $routeName => $method) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Settings route {$routeName} should exist");
            $this->assertContains($method, $route->methods());
        }
    }

    /** @test */
    public function verification_routes_accept_correct_parameters()
    {
        $notice = NotificationLog::factory()->create();

        // Timeline route requires ID parameter
        $response = $this->get(route('notices.verification.timeline', $notice->id));
        $response->assertStatus(200);

        // Patron route requires barcode parameter
        $response = $this->get(route('notices.verification.patron', ['barcode' => 'TEST123']));
        $response->assertStatus(200);
    }

    /** @test */
    public function all_dashboard_routes_return_valid_views()
    {
        $dashboardRoutes = [
            'notices.dashboard' => 'notices::dashboard.index',
            'notices.list' => 'notices::dashboard.notifications',
            'notices.analytics' => 'notices::dashboard.analytics',
            'notices.shoutbomb' => 'notices::dashboard.shoutbomb',
            'notices.troubleshooting' => 'notices::dashboard.troubleshooting',
            'notices.verification.index' => 'notices::dashboard.verification',
        ];

        foreach ($dashboardRoutes as $routeName => $viewName) {
            $response = $this->get(route($routeName));
            $response->assertStatus(200);
            $response->assertViewIs($viewName);
        }
    }

    /** @test */
    public function route_name_prefixes_are_consistent()
    {
        $routes = Route::getRoutes()->getRoutes();
        $noticeRoutes = array_filter($routes, function ($route) {
            return $route->getName() && str_starts_with($route->getName(), 'notices.');
        });

        $this->assertNotEmpty($noticeRoutes, 'Should have routes with notices. prefix');

        foreach ($noticeRoutes as $route) {
            $this->assertStringStartsWith('notices.', $route->getName());
        }
    }
}
