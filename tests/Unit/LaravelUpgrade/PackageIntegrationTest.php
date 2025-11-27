<?php

namespace Dcplibrary\Notices\Tests\Unit\LaravelUpgrade;

use Carbon\Carbon;
use Dcplibrary\Notices\Http\Resources\NotificationLogResource;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\NoticesServiceProvider;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Runner\Version;

class PackageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_verifies_orchestra_testbench_is_compatible()
    {
        // Verify Orchestra Testbench is loaded and working
        $this->assertInstanceOf(
            \Orchestra\Testbench\TestCase::class,
            $this
        );
    }

    /** @test */
    public function it_verifies_phpunit_version_compatibility()
    {
        // Verify PHPUnit version is 10.x or 11.x as per composer.json
        $version = Version::id();

        $this->assertTrue(
            version_compare($version, '10.0.0', '>=') &&
            version_compare($version, '12.0.0', '<'),
            "PHPUnit version {$version} should be between 10.0 and 12.0"
        );
    }

    /** @test */
    public function it_verifies_carbon_date_library_works()
    {
        $now = Carbon::today();
        $future = $now->copy()->addDays(7);
        $past = $now->copy()->subDays(7);

        $this->assertInstanceOf(Carbon::class, $now);
        $this->assertTrue($future->greaterThan($now));
        $this->assertTrue($past->lessThan($now));
        $this->assertEquals(14, $future->diffInDays($past, true));
    }

    /** @test */
    public function it_verifies_illuminate_support_collection_methods()
    {
        $collection = collect([1, 2, 3, 4, 5]);

        // Test various collection methods
        $this->assertEquals(5, $collection->count());
        $this->assertEquals([2, 4], $collection->filter(fn ($n) => $n % 2 === 0)->values()->all());
        $this->assertEquals([2, 4, 6, 8, 10], $collection->map(fn ($n) => $n * 2)->all());
        $this->assertTrue($collection->contains(3));
        $this->assertFalse($collection->contains(10));
    }

    /** @test */
    public function it_verifies_illuminate_database_query_builder_works()
    {
        $query = DB::table('notification_logs');

        $this->assertInstanceOf(
            Builder::class,
            $query
        );
    }

    /** @test */
    public function it_verifies_illuminate_console_commands_can_be_registered()
    {
        $commands = Artisan::all();

        // Verify our custom commands are registered
        $this->assertArrayHasKey('notices:test-connections', $commands);
        $this->assertArrayHasKey('notices:import-polaris', $commands);
        $this->assertArrayHasKey('notices:import-shoutbomb', $commands);
        $this->assertArrayHasKey('notices:aggregate', $commands);
    }

    /** @test */
    public function it_verifies_package_auto_discovery_works()
    {
        // Verify the package is auto-discovered via composer.json extra.laravel.providers
        $providers = app()->getLoadedProviders();

        $this->assertArrayHasKey(
            NoticesServiceProvider::class,
            $providers
        );
    }

    /** @test */
    public function it_verifies_json_resource_serialization_works()
    {
        $notification = NotificationLog::factory()->create();

        $resource = new NotificationLogResource($notification);
        $array = $resource->toArray(request());

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('patron_id', $array);
    }

    /** @test */
    public function it_verifies_request_validation_works()
    {
        $validator = Validator::make(
            [
                'patron_id' => 'not-a-number',
                'notification_date' => 'invalid-date',
            ],
            [
                'patron_id' => 'required|integer',
                'notification_date' => 'required|date',
            ]
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('patron_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('notification_date', $validator->errors()->toArray());
    }

    /** @test */
    public function it_verifies_pagination_resource_works()
    {
        NotificationLog::factory()->count(25)->create();

        $paginated = NotificationLog::paginate(10);
        $resource = NotificationLogResource::collection($paginated);

        $response = $resource->toResponse(request());

        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('links', $content);
        $this->assertArrayHasKey('meta', $content);
    }

    /** @test */
    public function it_verifies_config_repository_works()
    {
        $config = config('notices');

        $this->assertIsArray($config);
        $this->assertNotEmpty($config);
    }

    /** @test */
    public function it_verifies_route_model_binding_works()
    {
        $notification = NotificationLog::factory()->create();

        $response = $this->getJson(
            route('notices.api.logs.show', $notification)
        );

        $response->assertStatus(200);
        $this->assertEquals($notification->id, $response->json('data.id'));
    }

    /** @test */
    public function it_verifies_database_factory_works()
    {
        // Test that Eloquent factories work
        $notification = NotificationLog::factory()->create([
            'patron_id' => 88888,
        ]);

        $this->assertInstanceOf(
            NotificationLog::class,
            $notification
        );
        $this->assertEquals(88888, $notification->patron_id);
    }

    /** @test */
    public function it_verifies_database_factory_make_method_works()
    {
        // Test factory make (doesn't persist)
        $notification = NotificationLog::factory()->make();

        $this->assertInstanceOf(
            NotificationLog::class,
            $notification
        );
        $this->assertFalse($notification->exists);
    }

    /** @test */
    public function it_verifies_http_client_is_available()
    {
        $client = Http::fake();

        $this->assertNotNull($client);
    }

    /** @test */
    public function it_verifies_str_helper_functions_work()
    {
        $this->assertEquals('test_string', Str::snake('TestString'));
        $this->assertEquals('test-string', Str::kebab('TestString'));
        $this->assertEquals('TestString', Str::studly('test_string'));
        $this->assertTrue(Str::contains('hello world', 'world'));
        $this->assertTrue(Str::startsWith('hello world', 'hello'));
    }

    /** @test */
    public function it_verifies_arr_helper_functions_work()
    {
        $array = ['name' => 'John', 'age' => 30];

        $this->assertEquals('John', Arr::get($array, 'name'));
        $this->assertTrue(Arr::has($array, 'name'));
        $this->assertEquals(['name', 'age'], array_keys($array));
    }

    /** @test */
    public function it_verifies_schedule_can_be_defined()
    {
        $schedule = app()->make(Schedule::class);

        $this->assertInstanceOf(
            Schedule::class,
            $schedule
        );
    }

    /** @test */
    public function it_verifies_logger_is_available()
    {
        $logger = Log::channel('stack');

        $this->assertNotNull($logger);
    }

    /** @test */
    public function it_verifies_filesystem_operations_work()
    {
        $disk = Storage::fake('local');

        $disk->put('test.txt', 'test content');
        $this->assertTrue($disk->exists('test.txt'));
        $this->assertEquals('test content', $disk->get('test.txt'));

        $disk->delete('test.txt');
        $this->assertFalse($disk->exists('test.txt'));
    }

    /** @test */
    public function it_verifies_queue_system_is_available()
    {
        $queue = Queue::getFacadeRoot();

        $this->assertNotNull($queue);
    }
}
