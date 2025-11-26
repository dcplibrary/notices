<?php

namespace Dcplibrary\Notices\Tests\Feature\Api;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_list_notifications_via_api()
    {
        // Create test notifications
        NotificationLog::factory()->count(10)->create();

        $response = $this->getJson(route('notices.api.logs.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'patron_id',
                        'notification_date',
                        'notification_type',
                        'delivery_method',
                        'status',
                        'items',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    /** @test */
    public function it_can_filter_notifications_by_type()
    {
        NotificationLog::factory()->count(5)->create(['notification_type_id' => 4]); // Holds
        NotificationLog::factory()->count(3)->create(['notification_type_id' => 5]); // Overdues

        $response = $this->getJson(route('notices.api.logs.index', ['type_id' => 4]));

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data')));
    }

    /** @test */
    public function it_can_filter_notifications_by_date_range()
    {
        NotificationLog::factory()->create([
            'notification_date' => Carbon::parse('2025-11-01'),
        ]);
        NotificationLog::factory()->create([
            'notification_date' => Carbon::parse('2025-11-15'),
        ]);
        NotificationLog::factory()->create([
            'notification_date' => Carbon::parse('2025-11-30'),
        ]);

        $response = $this->getJson(route('notices.api.logs.index', [
            'start_date' => '2025-11-10',
            'end_date' => '2025-11-20',
        ]));

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    /** @test */
    public function it_can_filter_successful_notifications()
    {
        NotificationLog::factory()->count(7)->successful()->create();
        NotificationLog::factory()->count(3)->failed()->create();

        $response = $this->getJson(route('notices.api.logs.index', ['successful' => true]));

        $response->assertStatus(200);
        $this->assertEquals(7, count($response->json('data')));
    }

    /** @test */
    public function it_can_show_single_notification()
    {
        $notification = NotificationLog::factory()->create([
            'patron_id' => 12345,
            'notification_type_id' => 4,
        ]);

        $response = $this->getJson(route('notices.api.logs.show', $notification));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $notification->id,
                    'patron_id' => 12345,
                ],
            ]);
    }

    /** @test */
    public function it_can_get_notification_stats()
    {
        NotificationLog::factory()->count(10)->successful()->create();
        NotificationLog::factory()->count(2)->failed()->create();

        $response = $this->getJson(route('notices.api.logs.stats'));

        $response->assertStatus(200)
            ->assertJson([
                'total' => 12,
                'successful' => 10,
                'failed' => 2,
            ]);

        $this->assertArrayHasKey('success_rate', $response->json());
        $this->assertArrayHasKey('failure_rate', $response->json());
    }

    /** @test */
    public function it_respects_pagination_limits()
    {
        NotificationLog::factory()->count(50)->create();

        // Test default pagination
        $response = $this->getJson(route('notices.api.logs.index'));
        $this->assertLessThanOrEqual(20, count($response->json('data')));

        // Test custom pagination
        $response = $this->getJson(route('notices.api.logs.index', ['per_page' => 10]));
        $this->assertEquals(10, count($response->json('data')));

        // Test max pagination limit
        $response = $this->getJson(route('notices.api.logs.index', ['per_page' => 200]));
        $this->assertLessThanOrEqual(100, count($response->json('data')));
    }
}
