<?php

namespace Dcplibrary\Notices\Tests;

use Dcplibrary\Notices\NoticesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load and run package migrations for tests
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/Migrations');
        $this->artisan('migrate');
    }

    protected function getPackageProviders($app)
    {
        return [
            NoticesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Use in-memory SQLite database for testing (preferred)
        // Falls back to MySQL if SQLite is not available
        if (extension_loaded('pdo_sqlite')) {
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);

            // Mock Polaris connection (won't actually connect during tests)
            $app['config']->set('database.connections.polaris', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        } else {
            // Fallback to MySQL for testing
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_TEST_DATABASE', 'notifications_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);

            // Mock Polaris connection using MySQL
            $app['config']->set('database.connections.polaris', [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_TEST_DATABASE', 'notifications_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
        }

        // Load package config
        $app['config']->set('notices', require __DIR__ . '/../config/notices.php');

        // Provide an application key for encryption-dependent features
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        // Relax middleware for tests: disable Sanctum/auth requirements on package routes
        $app['config']->set('notices.api.middleware', ['api']);
        $app['config']->set('notices.dashboard.middleware', ['web']);
    }
}
