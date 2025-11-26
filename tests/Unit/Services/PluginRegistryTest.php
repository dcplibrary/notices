<?php

namespace Dcplibrary\Notices\Tests\Unit\Services;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Plugins\ShoutbombPlugin;
use Dcplibrary\Notices\Services\PluginRegistry;
use Dcplibrary\Notices\Tests\TestCase;
use InvalidArgumentException;

/**
 * Tests for the PluginRegistry.
 */
class PluginRegistryTest extends TestCase
{
    protected PluginRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PluginRegistry();
    }

    /** @test */
    public function it_can_register_a_plugin()
    {
        $plugin = new ShoutbombPlugin();

        $this->registry->register($plugin);

        $this->assertTrue($this->registry->has('shoutbomb'));
        $this->assertEquals(1, $this->registry->count());
    }

    /** @test */
    public function it_can_retrieve_a_registered_plugin()
    {
        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        $retrieved = $this->registry->get('shoutbomb');

        $this->assertNotNull($retrieved);
        $this->assertInstanceOf(ShoutbombPlugin::class, $retrieved);
        $this->assertEquals('shoutbomb', $retrieved->getName());
    }

    /** @test */
    public function it_returns_null_for_unregistered_plugin()
    {
        $plugin = $this->registry->get('nonexistent');

        $this->assertNull($plugin);
    }

    /** @test */
    public function it_throws_exception_when_registering_duplicate_plugin()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Plugin 'shoutbomb' is already registered");

        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);
        $this->registry->register($plugin); // Should throw
    }

    /** @test */
    public function it_returns_all_registered_plugins()
    {
        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        $all = $this->registry->all();

        $this->assertIsArray($all);
        $this->assertCount(1, $all);
        $this->assertInstanceOf(ShoutbombPlugin::class, $all[0]);
    }

    /** @test */
    public function it_returns_only_enabled_plugins()
    {
        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        $enabled = $this->registry->enabled();

        $this->assertIsArray($enabled);
        $this->assertCount(1, $enabled);
        $this->assertTrue($enabled[0]->isEnabled());
    }

    /** @test */
    public function it_maps_delivery_option_ids_to_plugins()
    {
        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        // Shoutbomb handles delivery option IDs 3 (Voice) and 8 (SMS)
        $voicePlugin = $this->registry->getByDeliveryOption(3);
        $smsPlugin = $this->registry->getByDeliveryOption(8);

        $this->assertNotNull($voicePlugin);
        $this->assertNotNull($smsPlugin);
        $this->assertInstanceOf(ShoutbombPlugin::class, $voicePlugin);
        $this->assertInstanceOf(ShoutbombPlugin::class, $smsPlugin);
    }

    /** @test */
    public function it_returns_null_for_unmapped_delivery_option()
    {
        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        $unmapped = $this->registry->getByDeliveryOption(999);

        $this->assertNull($unmapped);
    }

    /** @test */
    public function it_finds_plugin_for_notice_by_delivery_option()
    {
        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        // Create a mock notice with voice delivery (option 3)
        $notice = new NotificationLog();
        $notice->delivery_option_id = 3;

        $foundPlugin = $this->registry->findPluginForNotice($notice);

        $this->assertNotNull($foundPlugin);
        $this->assertInstanceOf(ShoutbombPlugin::class, $foundPlugin);
    }

    /** @test */
    public function it_returns_null_when_no_plugin_can_verify_notice()
    {
        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        // Create a notice with unsupported delivery option
        $notice = new NotificationLog();
        $notice->delivery_option_id = 999;

        $foundPlugin = $this->registry->findPluginForNotice($notice);

        $this->assertNull($foundPlugin);
    }

    /** @test */
    public function it_correctly_reports_has_plugins()
    {
        $this->assertFalse($this->registry->hasPlugins());

        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        $this->assertTrue($this->registry->hasPlugins());
    }

    /** @test */
    public function it_correctly_counts_plugins()
    {
        $this->assertEquals(0, $this->registry->count());

        $plugin = new ShoutbombPlugin();
        $this->registry->register($plugin);

        $this->assertEquals(1, $this->registry->count());
    }
}
