<?php

namespace Dcplibrary\Notices\Tests\Unit;

use Dcplibrary\Notices\Models\LookupTableOverride;
use Dcplibrary\Notices\Services\LookupTableService;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Unit tests for LookupTableService.
 *
 * Verifies that lookup table queries correctly apply overrides and filter enabled items.
 * The service exposes labels as `display_label` (COALESCE of override custom_label and
 * the underlying description / delivery_option columns).
 */
class LookupTableServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LookupTableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LookupTableService::class);
    }

    /**
     * Test getting enabled notification types.
     */
    public function test_get_enabled_notification_types(): void
    {
        // Reset table so we can control fixtures (package test harness seeds lookup tables).
        DB::table('notification_types')->delete();

        DB::table('notification_types')->insert([
            ['id' => 2, 'description' => 'Hold Notice'],
            ['id' => 7, 'description' => 'Renewal Notice'],
            ['id' => 8, 'description' => 'Fine'],
        ]);

        $types = $this->service->getEnabledNotificationTypes();

        $this->assertCount(3, $types);
        $this->assertEquals('Fine', $types->where('id', 8)->first()->display_label);
        $this->assertEquals('Hold Notice', $types->where('id', 2)->first()->display_label);
    }

    /**
     * Test notification types with custom labels.
     */
    public function test_notification_types_with_custom_labels(): void
    {
        DB::table('notification_types')->delete();
        DB::table('notification_types')->insert([
            ['id' => 8, 'description' => 'Fine'],
        ]);

        // Add custom label override
        LookupTableOverride::create([
            'table_name' => 'notification_types',
            'record_id' => 8,
            'custom_label' => '2nd Overdue (7 days)',
            'is_enabled' => true,
        ]);

        $types = $this->service->getEnabledNotificationTypes();

        $this->assertCount(1, $types);
        $this->assertEquals('2nd Overdue (7 days)', $types->first()->display_label);
    }

    /**
     * Test disabled notification types are filtered out.
     */
    public function test_disabled_notification_types_are_filtered(): void
    {
        DB::table('notification_types')->delete();
        DB::table('notification_types')->insert([
            ['id' => 2, 'description' => 'Hold Notice'],
            ['id' => 7, 'description' => 'Renewal Notice'],
        ]);

        // Disable one
        LookupTableOverride::create([
            'table_name' => 'notification_types',
            'record_id' => 7,
            'is_enabled' => false,
        ]);

        $types = $this->service->getEnabledNotificationTypes();

        $this->assertCount(1, $types);
        $this->assertEquals(2, $types->first()->id);
        $this->assertEquals('Hold Notice', $types->first()->display_label);
    }

    /**
     * Test getting enabled delivery options.
     */
    public function test_get_enabled_delivery_options(): void
    {
        DB::table('delivery_options')->delete();
        DB::table('delivery_options')->insert([
            ['id' => 1, 'delivery_option' => 'Mailing Address'],
            ['id' => 2, 'delivery_option' => 'E-mail'],
            ['id' => 3, 'delivery_option' => 'Phone 1'],
        ]);

        $options = $this->service->getEnabledDeliveryOptions();

        $this->assertCount(3, $options);
        $this->assertEquals('E-mail', $options->where('id', 2)->first()->display_label);
    }

    /**
     * Test delivery options with custom labels.
     */
    public function test_delivery_options_with_custom_labels(): void
    {
        DB::table('delivery_options')->delete();
        DB::table('delivery_options')->insert([
            ['id' => 3, 'delivery_option' => 'Phone 1'],
        ]);

        // Add custom label override
        LookupTableOverride::create([
            'table_name' => 'delivery_options',
            'record_id' => 3,
            'custom_label' => 'Voice',
            'is_enabled' => true,
        ]);

        $options = $this->service->getEnabledDeliveryOptions();

        $this->assertCount(1, $options);
        $this->assertEquals('Voice', $options->first()->display_label);
    }

    /**
     * Test disabled delivery options are filtered out.
     */
    public function test_disabled_delivery_options_are_filtered(): void
    {
        DB::table('delivery_options')->delete();
        DB::table('delivery_options')->insert([
            ['id' => 1, 'delivery_option' => 'Mailing Address'],
            ['id' => 2, 'delivery_option' => 'E-mail'],
        ]);

        // Disable one
        LookupTableOverride::create([
            'table_name' => 'delivery_options',
            'record_id' => 1,
            'is_enabled' => false,
        ]);

        $options = $this->service->getEnabledDeliveryOptions();

        $this->assertCount(1, $options);
        $this->assertEquals(2, $options->first()->id);
        $this->assertEquals('E-mail', $options->first()->display_label);
    }

    /**
     * Test that results are ordered correctly.
     */
    public function test_results_are_ordered(): void
    {
        DB::table('delivery_options')->delete();
        DB::table('delivery_options')->insert([
            ['id' => 3, 'delivery_option' => 'Phone 1'],
            ['id' => 1, 'delivery_option' => 'Mailing Address'],
            ['id' => 2, 'delivery_option' => 'E-mail'],
        ]);

        $options = $this->service->getEnabledDeliveryOptions();

        $this->assertCount(3, $options);
        // Should be ordered by display_label
        $this->assertEquals('E-mail', $options->first()->display_label);
        $this->assertEquals('Phone 1', $options->last()->display_label);
    }
}
