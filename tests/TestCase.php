<?php

namespace Dcplibrary\Notices\Tests;

use Dcplibrary\Notices\NoticesServiceProvider;
use Dcplibrary\Notices\Database\Seeders\LookupTablesSeeder;
use Dcplibrary\Notices\Tests\Support\FakeStaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The package now uses caching for dashboard-level aggregates; ensure test isolation.
        Cache::flush();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Dcplibrary\\Notices\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        // Many tests insert rows into tables that have foreign keys to lookup tables.
        // Seed those lookup tables automatically for package tests.
        $this->seed(LookupTablesSeeder::class);

        // Staff layouts from dcplibrary/ui call method_exists(auth()->user(), ...).
        // Feature tests disable Authenticate middleware, so provide a stub admin user.
        $this->actingAs(new FakeStaffUser());
    }

    protected function getPackageProviders($app)
    {
        return [
            NoticesServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        $testDb = strtolower((string) env('NOTICES_TEST_DB', 'sqlite'));

        if ('mysql' === $testDb) {
            config()->set('database.connections.testing', [
                'driver'         => 'mysql',
                'host'           => env('DB_HOST', '127.0.0.1'),
                'port'           => (int) env('DB_PORT', 3306),
                'database'       => env('DB_DATABASE', 'notices_test'),
                'username'       => env('DB_USERNAME', 'notices'),
                'password'       => env('DB_PASSWORD', 'notices'),
                'unix_socket'    => env('DB_SOCKET', ''),
                'charset'        => 'utf8mb4',
                'collation'      => 'utf8mb4_unicode_ci',
                'prefix'         => '',
                'prefix_indexes' => true,
                'strict'         => true,
                'engine'         => null,
            ]);
        } else {
            config()->set('database.connections.testing', [
                'driver'                  => 'sqlite',
                'database'                => ':memory:',
                'prefix'                  => '',
                'foreign_key_constraints' => true,
            ]);
        }

        // Provide an application key so encrypted services (cookies, etc.) work in tests.
        // Generate a valid 32-byte key for AES-256-CBC.
        $key = base64_encode(random_bytes(32));
        config()->set('app.key', 'base64:' . $key);
        config()->set('app.cipher', 'AES-256-CBC');

        // This package assumes America/Chicago for its "business day" logic and
        // stores timestamps in UTC with app-timezone hydration.
        config()->set('app.timezone', 'America/Chicago');
        date_default_timezone_set('America/Chicago');

        // Use in-memory cache store for tests.
        config()->set('cache.default', 'array');
    }
}
