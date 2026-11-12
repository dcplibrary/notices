<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test suite for scheduled task registration.
 *
 * Verifies that the NoticesServiceProvider correctly registers all four daily
 * import schedules at the expected times (8:30 AM, 9:30 AM, 1:30 PM, 5:30 PM).
 *
 * @package Dcplibrary\Notices\Tests\Feature
 */
class ScheduledTasksTest extends TestCase
{
    #[Test]
    public function it_registers_scheduled_import_tasks()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $importEvents = $events->filter(function ($event) {
            return str_contains($event->command ?? '', 'notices:import');
        });

        // Should have 4 scheduled import tasks
        $this->assertGreaterThanOrEqual(4, $importEvents->count(),
            'Expected at least 4 scheduled import tasks to be registered');
    }

    #[Test]
    public function it_schedules_morning_import_at_8_30_am()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $morningImport = $events->first(function ($event) {
            return str_contains($event->command ?? '', 'notices:import')
                && str_contains($event->expression ?? '', '30 8');
        });

        $this->assertNotNull($morningImport,
            'Morning import at 8:30 AM should be scheduled');
    }

    #[Test]
    public function it_schedules_second_holds_import_at_9_30_am()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $secondImport = $events->first(function ($event) {
            return str_contains($event->command ?? '', 'notices:import')
                && str_contains($event->expression ?? '', '30 9');
        });

        $this->assertNotNull($secondImport,
            'Second holds import at 9:30 AM should be scheduled');
    }

    #[Test]
    public function it_schedules_afternoon_import_at_1_30_pm()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $afternoonImport = $events->first(function ($event) {
            return str_contains($event->command ?? '', 'notices:import')
                && str_contains($event->expression ?? '', '30 13');
        });

        $this->assertNotNull($afternoonImport,
            'Afternoon import at 1:30 PM should be scheduled');
    }

    #[Test]
    public function it_schedules_evening_import_at_5_30_pm()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $eveningImport = $events->first(function ($event) {
            return str_contains($event->command ?? '', 'notices:import')
                && str_contains($event->expression ?? '', '30 17');
        });

        $this->assertNotNull($eveningImport,
            'Evening import at 5:30 PM should be scheduled');
    }

    #[Test]
    public function scheduled_tasks_use_correct_command()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $importEvents = $events->filter(function ($event) {
            return str_contains($event->command ?? '', 'notices:import');
        });

        foreach ($importEvents as $event) {
            $this->assertStringContainsString('notices:import', $event->command,
                'Scheduled task should use notices:import command');

            $this->assertStringContainsString('--date=today', $event->command,
                'Scheduled task should include --date=today parameter');
        }
    }

    #[Test]
    public function scheduled_tasks_run_in_background()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $importEvents = $events->filter(function ($event) {
            return str_contains($event->command ?? '', 'notices:import');
        });

        foreach ($importEvents as $event) {
            $this->assertTrue($event->runInBackground,
                'Scheduled tasks should be configured to run in background');
        }
    }

    #[Test]
    public function it_creates_all_expected_schedule_times()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $importEvents = $events->filter(function ($event) {
            return str_contains($event->command ?? '', 'notices:import');
        });

        $expectedTimes = [
            '30 8',   // 8:30 AM
            '30 9',   // 9:30 AM
            '30 13',  // 1:30 PM
            '30 17',  // 5:30 PM
        ];

        foreach ($expectedTimes as $time) {
            $hasTime = $importEvents->contains(function ($event) use ($time) {
                return str_contains($event->expression ?? '', $time);
            });

            $this->assertTrue($hasTime,
                "Schedule should include import at time: {$time}");
        }
    }

    #[Test]
    public function scheduled_tasks_are_daily_not_weekly_or_monthly()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $importEvents = $events->filter(function ($event) {
            return str_contains($event->command ?? '', 'notices:import');
        });

        foreach ($importEvents as $event) {
            // Cron expression should have all days (* in day-of-month and month fields)
            $parts = explode(' ', $event->expression);

            // Cron format: minute hour day-of-month month day-of-week
            $this->assertEquals('*', $parts[2] ?? '', 'Should run every day of month');
            $this->assertEquals('*', $parts[3] ?? '', 'Should run every month');
        }
    }
}
