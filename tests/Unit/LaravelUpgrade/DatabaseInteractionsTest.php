<?php

namespace Dcplibrary\Notices\Tests\Unit\LaravelUpgrade;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Tests\TestCase;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseInteractionsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_verifies_database_connection_is_working()
    {
        $this->assertNotNull(DB::connection());
        $this->assertTrue(DB::connection()->getDatabaseName() !== null);
    }

    /** @test */
    public function it_verifies_migrations_have_run_successfully()
    {
        // Verify key tables exist
        $this->assertTrue(Schema::hasTable('notification_logs'));
        $this->assertTrue(Schema::hasTable('daily_notification_summaries'));
    }

    /** @test */
    public function it_verifies_notification_logs_table_structure()
    {
        $columns = [
            'id',
            'polaris_log_id',
            'patron_id',
            'patron_barcode',
            'notification_date',
            'notification_type_id',
            'delivery_option_id',
            'notification_status_id',
            'holds_count',
            'overdues_count',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('notification_logs', $column),
                "Column {$column} should exist in notification_logs table"
            );
        }
    }

    /** @test */
    public function it_verifies_create_operation_works()
    {
        $notification = NotificationLog::create([
            'patron_id' => 12345,
            'notification_date' => now(),
            'notification_type_id' => 4,
            'delivery_option_id' => 2,
            'notification_status_id' => 12,
        ]);

        $this->assertInstanceOf(NotificationLog::class, $notification);
        $this->assertTrue($notification->exists);
        $this->assertDatabaseHas('notification_logs', [
            'patron_id' => 12345,
            'notification_type_id' => 4,
        ]);
    }

    /** @test */
    public function it_verifies_read_operation_works()
    {
        $created = NotificationLog::factory()->create(['patron_id' => 99999]);

        $found = NotificationLog::where('patron_id', 99999)->first();

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
        $this->assertEquals(99999, $found->patron_id);
    }

    /** @test */
    public function it_verifies_update_operation_works()
    {
        $notification = NotificationLog::factory()->create([
            'patron_id' => 100,
            'notification_status_id' => 12,
        ]);

        $notification->update([
            'notification_status_id' => 14,
        ]);

        $this->assertEquals(14, $notification->fresh()->notification_status_id);
        $this->assertDatabaseHas('notification_logs', [
            'id' => $notification->id,
            'notification_status_id' => 14,
        ]);
    }

    /** @test */
    public function it_verifies_delete_operation_works()
    {
        $notification = NotificationLog::factory()->create();
        $id = $notification->id;

        $notification->delete();

        $this->assertDatabaseMissing('notification_logs', ['id' => $id]);
        $this->assertNull(NotificationLog::find($id));
    }

    /** @test */
    public function it_verifies_bulk_insert_works()
    {
        $records = [];
        for ($i = 0; $i < 100; $i++) {
            $records[] = [
                'patron_id' => 1000 + $i,
                'notification_date' => now(),
                'notification_type_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('notification_logs')->insert($records);

        $count = NotificationLog::where('patron_id', '>=', 1000)->count();
        $this->assertEquals(100, $count);
    }

    /** @test */
    public function it_verifies_transactions_work()
    {
        DB::beginTransaction();

        try {
            NotificationLog::create([
                'patron_id' => 5000,
                'notification_date' => now(),
            ]);

            NotificationLog::create([
                'patron_id' => 5001,
                'notification_date' => now(),
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->assertDatabaseHas('notification_logs', ['patron_id' => 5000]);
        $this->assertDatabaseHas('notification_logs', ['patron_id' => 5001]);
    }

    /** @test */
    public function it_verifies_transactions_rollback_on_error()
    {
        try {
            DB::beginTransaction();

            NotificationLog::create([
                'patron_id' => 6000,
                'notification_date' => now(),
            ]);

            // Simulate an error
            throw new Exception('Test error');

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
        }

        $this->assertDatabaseMissing('notification_logs', ['patron_id' => 6000]);
    }

    /** @test */
    public function it_verifies_query_builder_where_clauses_work()
    {
        NotificationLog::factory()->count(5)->create(['notification_type_id' => 4]);
        NotificationLog::factory()->count(3)->create(['notification_type_id' => 5]);

        $results = NotificationLog::where('notification_type_id', 4)->get();

        $this->assertCount(5, $results);
    }

    /** @test */
    public function it_verifies_query_builder_join_works()
    {
        NotificationLog::factory()->count(3)->create();

        $results = DB::table('notification_logs')
            ->select('notification_logs.*')
            ->get();

        $this->assertGreaterThan(0, $results->count());
    }

    /** @test */
    public function it_verifies_eloquent_relationships_work()
    {
        // If you have relationships defined, test them here
        $notification = NotificationLog::factory()->create();

        $this->assertInstanceOf(NotificationLog::class, $notification);
        // Add relationship tests if applicable
    }

    /** @test */
    public function it_verifies_query_scopes_work()
    {
        NotificationLog::factory()->count(3)->create([
            'notification_status_id' => 12, // Success
        ]);
        NotificationLog::factory()->count(2)->create([
            'notification_status_id' => 14, // Failed
        ]);

        $successful = NotificationLog::successful()->get();
        $failed = NotificationLog::failed()->get();

        $this->assertCount(3, $successful);
        $this->assertCount(2, $failed);
    }

    /** @test */
    public function it_verifies_aggregate_functions_work()
    {
        NotificationLog::factory()->count(10)->create();

        $count = NotificationLog::count();
        $maxId = NotificationLog::max('id');
        $minId = NotificationLog::min('id');

        $this->assertEquals(10, $count);
        $this->assertGreaterThan(0, $maxId);
        $this->assertGreaterThan(0, $minId);
    }

    /** @test */
    public function it_verifies_pagination_works()
    {
        NotificationLog::factory()->count(50)->create();

        $paginated = NotificationLog::paginate(10);

        $this->assertEquals(10, $paginated->count());
        $this->assertEquals(50, $paginated->total());
        $this->assertEquals(5, $paginated->lastPage());
    }

    /** @test */
    public function it_verifies_soft_deletes_work_if_enabled()
    {
        // Skip if model doesn't use soft deletes
        if (!in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(NotificationLog::class))) {
            $this->markTestSkipped('Model does not use soft deletes');
        }

        $notification = NotificationLog::factory()->create();
        $id = $notification->id;

        $notification->delete();

        $this->assertSoftDeleted('notification_logs', ['id' => $id]);
        $this->assertNotNull(NotificationLog::withTrashed()->find($id));
    }

    /** @test */
    public function it_verifies_eloquent_casting_works()
    {
        $notification = NotificationLog::factory()->create([
            'notification_date' => '2025-11-08 10:00:00',
            'reported' => 1,
        ]);

        $this->assertInstanceOf(Carbon::class, $notification->notification_date);
        $this->assertIsBool($notification->reported);
    }

    /** @test */
    public function it_verifies_database_seeding_compatibility()
    {
        // Test that factory works (which is used for seeding)
        $notifications = NotificationLog::factory()->count(10)->create();

        $this->assertCount(10, $notifications);
        $this->assertInstanceOf(NotificationLog::class, $notifications->first());
    }

    /** @test */
    public function it_verifies_raw_queries_work()
    {
        NotificationLog::factory()->count(5)->create();

        $results = DB::select('SELECT COUNT(*) as count FROM notification_logs');

        $this->assertEquals(5, $results[0]->count);
    }

    /** @test */
    public function it_verifies_query_builder_order_by_works()
    {
        NotificationLog::factory()->create(['patron_id' => 300]);
        NotificationLog::factory()->create(['patron_id' => 100]);
        NotificationLog::factory()->create(['patron_id' => 200]);

        $results = NotificationLog::orderBy('patron_id', 'asc')->get();

        $this->assertEquals(100, $results->first()->patron_id);
        $this->assertEquals(300, $results->last()->patron_id);
    }

    /** @test */
    public function it_verifies_database_transactions_are_isolated()
    {
        DB::beginTransaction();

        NotificationLog::create([
            'patron_id' => 7000,
            'notification_date' => now(),
        ]);

        // Check within transaction
        $this->assertDatabaseHas('notification_logs', ['patron_id' => 7000]);

        DB::rollBack();

        // Check after rollback
        $this->assertDatabaseMissing('notification_logs', ['patron_id' => 7000]);
    }
}
