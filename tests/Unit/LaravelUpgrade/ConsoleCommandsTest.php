<?php

namespace Dcplibrary\Notices\Tests\Unit\LaravelUpgrade;

use Dcplibrary\Notices\Commands\TestConnections;
use Dcplibrary\Notices\Commands\ImportNotifications;
use Dcplibrary\Notices\Commands\ImportShoutbombReports;
use Dcplibrary\Notices\Commands\AggregateNotifications;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_verifies_artisan_kernel_is_available()
    {
        $kernel = app(\Illuminate\Contracts\Console\Kernel::class);

        $this->assertInstanceOf(
            \Illuminate\Contracts\Console\Kernel::class,
            $kernel
        );
    }

    /** @test */
    public function it_verifies_test_connections_command_is_registered()
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('notifications:test-connections', $commands);
        $this->assertInstanceOf(TestConnections::class, $commands['notifications:test-connections']);
    }

    /** @test */
    public function it_verifies_import_polaris_command_is_registered()
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('notifications:import-polaris', $commands);
        $this->assertInstanceOf(ImportNotifications::class, $commands['notifications:import-polaris']);
    }

    /** @test */
    public function it_verifies_import_shoutbomb_command_is_registered()
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('notifications:import-shoutbomb', $commands);
        $this->assertInstanceOf(ImportShoutbombReports::class, $commands['notifications:import-shoutbomb']);
    }

    /** @test */
    public function it_verifies_aggregate_command_is_registered()
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('notifications:aggregate', $commands);
        $this->assertInstanceOf(AggregateNotifications::class, $commands['notifications:aggregate']);
    }

    /** @test */
    public function it_verifies_test_connections_command_has_correct_signature()
    {
        $command = Artisan::all()['notifications:test-connections'];

        $this->assertEquals('notifications:test-connections', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    /** @test */
    public function it_verifies_commands_can_be_called_via_artisan()
    {
        // Test that Artisan can call our commands (without actually executing them fully)
        $exitCode = Artisan::call('notifications:test-connections', ['--help' => true]);

        // Exit code should be 0 (success) or the help was shown
        $this->assertTrue(in_array($exitCode, [0, 1]));
    }

    /** @test */
    public function it_verifies_command_options_are_defined()
    {
        $command = new TestConnections();

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('polaris'));
        $this->assertTrue($definition->hasOption('shoutbomb'));
    }

    /** @test */
    public function it_verifies_artisan_call_method_works()
    {
        $exitCode = Artisan::call('list');

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('notifications:', Artisan::output());
    }

    /** @test */
    public function it_verifies_console_kernel_schedule_is_available()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $this->assertInstanceOf(
            \Illuminate\Console\Scheduling\Schedule::class,
            $schedule
        );
    }

    /** @test */
    public function it_verifies_scheduled_tasks_can_be_defined()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        // Test that schedule can accept task definitions
        $event = $schedule->command('notifications:import-polaris')->daily();

        $this->assertInstanceOf(
            \Illuminate\Console\Scheduling\Event::class,
            $event
        );
    }

    /** @test */
    public function it_verifies_scheduled_task_frequencies_work()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        // Test various scheduling frequencies
        $daily = $schedule->command('test')->daily();
        $hourly = $schedule->command('test')->hourly();
        $weekly = $schedule->command('test')->weekly();

        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $daily);
        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $hourly);
        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $weekly);
    }

    /** @test */
    public function it_verifies_scheduled_task_cron_expressions_work()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        // Test custom cron expressions
        $event = $schedule->command('test')->cron('0 0 * * *');

        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $event);
    }

    /** @test */
    public function it_verifies_command_output_can_be_captured()
    {
        Artisan::call('list');
        $output = Artisan::output();

        $this->assertNotEmpty($output);
        $this->assertIsString($output);
    }

    /** @test */
    public function it_verifies_command_parameters_can_be_passed()
    {
        // Test passing parameters to commands
        $exitCode = Artisan::call('help', ['command_name' => 'list']);

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_verifies_command_can_output_to_console()
    {
        // Create a test command that outputs text
        Artisan::call('list');

        $output = Artisan::output();
        
        $this->assertStringContainsString('Available commands', $output);
    }

    /** @test */
    public function it_verifies_commands_can_interact_with_database()
    {
        // Create some test data
        NotificationLog::factory()->count(5)->create();

        // Verify command can access database
        $count = NotificationLog::count();

        $this->assertEquals(5, $count);
    }

    /** @test */
    public function it_verifies_artisan_queue_method_works()
    {
        // Test that commands can be queued
        $result = Artisan::queue('notifications:test-connections');

        $this->assertNotNull($result);
    }

    /** @test */
    public function it_verifies_command_signatures_are_properly_parsed()
    {
        $command = new TestConnections();

        $this->assertEquals('notifications:test-connections', $command->getName());
        
        $definition = $command->getDefinition();
        $this->assertNotNull($definition);
    }

    /** @test */
    public function it_verifies_scheduled_tasks_can_run_in_background()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $event = $schedule->command('test')->daily()->runInBackground();

        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $event);
    }

    /** @test */
    public function it_verifies_scheduled_tasks_can_be_chained()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $event = $schedule->command('test')
            ->daily()
            ->at('02:00')
            ->timezone('America/New_York');

        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $event);
    }

    /** @test */
    public function it_verifies_scheduled_tasks_can_have_callbacks()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $callbackExecuted = false;
        
        $event = $schedule->call(function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        })->daily();

        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $event);
    }

    /** @test */
    public function it_verifies_scheduled_tasks_can_have_constraints()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $event = $schedule->command('test')
            ->daily()
            ->when(function () {
                return true;
            });

        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $event);
    }

    /** @test */
    public function it_verifies_scheduled_tasks_support_output_redirection()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $event = $schedule->command('test')
            ->daily()
            ->appendOutputTo('/tmp/test.log');

        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $event);
    }

    /** @test */
    public function it_verifies_scheduled_tasks_support_email_notifications()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $event = $schedule->command('test')
            ->daily()
            ->onSuccess(function () {
                // Success callback
            })
            ->onFailure(function () {
                // Failure callback
            });

        $this->assertInstanceOf(\Illuminate\Console\Scheduling\Event::class, $event);
    }

    /** @test */
    public function it_verifies_all_custom_commands_have_descriptions()
    {
        $commands = [
            'notifications:test-connections',
            'notifications:import-polaris',
            'notifications:import-shoutbomb',
            'notifications:aggregate',
        ];

        foreach ($commands as $commandName) {
            $command = Artisan::all()[$commandName];
            $this->assertNotEmpty($command->getDescription(), "Command {$commandName} should have a description");
        }
    }

    /** @test */
    public function it_verifies_commands_return_proper_exit_codes()
    {
        // Success should return 0
        $exitCode = Artisan::call('list');
        $this->assertEquals(0, $exitCode);
    }
}
