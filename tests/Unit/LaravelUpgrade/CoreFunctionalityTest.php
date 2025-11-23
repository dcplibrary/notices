<?php

namespace Dcplibrary\Notices\Tests\Unit\LaravelUpgrade;

use Dcplibrary\Notices\NoticesServiceProvider;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

class CoreFunctionalityTest extends TestCase
{
    /** @test */
    public function it_verifies_laravel_version_compatibility()
    {
        $laravelVersion = App::version();
        
        // Verify Laravel version is 11.x or 12.x as per composer.json
        $this->assertTrue(
            version_compare($laravelVersion, '11.0.0', '>=') && 
            version_compare($laravelVersion, '13.0.0', '<'),
            "Laravel version {$laravelVersion} should be between 11.0 and 13.0"
        );
    }

    /** @test */
    public function it_verifies_php_version_compatibility()
    {
        $phpVersion = PHP_VERSION;
        
        // Verify PHP version is 8.1 or higher as per composer.json
        $this->assertTrue(
            version_compare($phpVersion, '8.1.0', '>='),
            "PHP version {$phpVersion} should be 8.1 or higher"
        );
    }

    /** @test */
    public function it_verifies_service_provider_is_loaded()
    {
        $loadedProviders = App::getLoadedProviders();
        
        $this->assertArrayHasKey(
            NoticesServiceProvider::class,
            $loadedProviders,
            'NoticesServiceProvider should be loaded'
        );
    }

    /** @test */
    public function it_verifies_config_is_loaded_correctly()
    {
        $config = Config::get('notices');
        
        $this->assertIsArray($config, 'Notifications config should be loaded');
        $this->assertArrayHasKey('notification_types', $config);
        $this->assertArrayHasKey('delivery_options', $config);
        $this->assertArrayHasKey('notification_statuses', $config);
    }

    /** @test */
    public function it_verifies_routes_are_registered()
    {
        $routes = Route::getRoutes();
        $routeNames = [];
        
        foreach ($routes as $route) {
            $routeNames[] = $route->getName();
        }
        
        // Verify key API routes are registered
        $this->assertContains('notices.api.logs.index', $routeNames);
        $this->assertContains('notices.api.logs.show', $routeNames);
        $this->assertContains('notices.api.logs.stats', $routeNames);
        $this->assertContains('notices.api.summaries.index', $routeNames);
        $this->assertContains('notices.api.analytics.overview', $routeNames);
        $this->assertContains('notices.api.shoutbomb.deliveries', $routeNames);
    }

    /** @test */
    public function it_verifies_illuminate_support_package_compatibility()
    {
        // Test that core Illuminate Support features work
        $collection = collect([1, 2, 3, 4, 5]);
        
        $this->assertEquals(15, $collection->sum());
        $this->assertEquals(3, $collection->avg());
        $this->assertTrue($collection->contains(3));
    }

    /** @test */
    public function it_verifies_illuminate_database_package_compatibility()
    {
        // Verify database connection can be established
        $this->assertNotNull(
            \Illuminate\Support\Facades\DB::connection(),
            'Database connection should be available'
        );
    }

    /** @test */
    public function it_verifies_illuminate_console_package_compatibility()
    {
        $artisan = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        
        $this->assertInstanceOf(
            \Illuminate\Contracts\Console\Kernel::class,
            $artisan,
            'Console kernel should be available'
        );
    }

    /** @test */
    public function it_verifies_middleware_stack_is_functioning()
    {
        $response = $this->getJson(route('notices.api.logs.index'));
        
        // Verify middleware is applied (headers, JSON response, etc.)
        $response->assertHeader('Content-Type', 'application/json');
    }

    /** @test */
    public function it_verifies_exception_handling_works()
    {
        // Test that invalid routes return proper error responses
        $response = $this->getJson('/api/notifications/invalid-endpoint-that-does-not-exist');
        
        $this->assertTrue(
            in_array($response->status(), [404, 500]),
            'Invalid routes should return error status codes'
        );
    }

    /** @test */
    public function it_verifies_validation_system_works()
    {
        // Test that Laravel validation is functioning
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['email' => 'invalid-email'],
            ['email' => 'required|email']
        );
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /** @test */
    public function it_verifies_cache_system_is_available()
    {
        \Illuminate\Support\Facades\Cache::put('test_key', 'test_value', 60);
        $value = \Illuminate\Support\Facades\Cache::get('test_key');
        
        $this->assertEquals('test_value', $value);
        
        \Illuminate\Support\Facades\Cache::forget('test_key');
    }

    /** @test */
    public function it_verifies_event_system_is_functioning()
    {
        $eventFired = false;
        
        \Illuminate\Support\Facades\Event::listen('test.event', function () use (&$eventFired) {
            $eventFired = true;
        });
        
        \Illuminate\Support\Facades\Event::dispatch('test.event');
        
        $this->assertTrue($eventFired, 'Event system should be functioning');
    }

    /** @test */
    public function it_verifies_dependency_injection_container_works()
    {
        $resolved = App::make(\Illuminate\Contracts\Foundation\Application::class);
        
        $this->assertInstanceOf(
            \Illuminate\Contracts\Foundation\Application::class,
            $resolved,
            'Dependency injection container should resolve instances'
        );
    }

    /** @test */
    public function it_verifies_facades_are_working()
    {
        // Test multiple facades to ensure they're functioning
        $this->assertNotNull(Config::get('app.name'));
        $this->assertIsArray(Route::getRoutes()->getRoutes());
        $this->assertNotNull(App::environment());
    }
}
