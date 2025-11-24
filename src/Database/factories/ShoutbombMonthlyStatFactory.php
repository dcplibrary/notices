<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\ShoutbombMonthlyStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Dcplibrary\Notices\Models\ShoutbombMonthlyStat>
 */
class ShoutbombMonthlyStatFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ShoutbombMonthlyStat::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reportMonth = $this->faker->dateTimeBetween('-12 months', 'now');

        return [
            'outlook_message_id' => $this->faker->unique()->uuid(),
            'subject' => 'Shoutbomb Rpt - ' . $reportMonth->format('F Y'),
            'report_month' => $reportMonth,
            'branch_name' => $this->faker->randomElement(['Main Library', 'Owensboro Branch', 'Whitesville Branch', null]),

            // Hold notices
            'hold_text_notices' => $this->faker->numberBetween(100, 500),
            'hold_text_reminders' => $this->faker->numberBetween(50, 200),
            'hold_voice_notices' => $this->faker->numberBetween(50, 200),
            'hold_voice_reminders' => $this->faker->numberBetween(20, 100),

            // Overdue notices
            'overdue_text_notices' => $this->faker->numberBetween(200, 600),
            'overdue_text_eligible_renewal' => $this->faker->numberBetween(100, 300),
            'overdue_text_ineligible_renewal' => $this->faker->numberBetween(50, 150),
            'overdue_text_renewed_successfully' => $this->faker->numberBetween(80, 250),
            'overdue_text_renewed_unsuccessfully' => $this->faker->numberBetween(10, 50),
            'overdue_voice_notices' => $this->faker->numberBetween(100, 300),
            'overdue_voice_eligible_renewal' => $this->faker->numberBetween(50, 150),
            'overdue_voice_ineligible_renewal' => $this->faker->numberBetween(30, 100),

            // Renewal notices
            'renewal_text_notices' => $this->faker->numberBetween(150, 400),
            'renewal_text_eligible' => $this->faker->numberBetween(100, 300),
            'renewal_text_ineligible' => $this->faker->numberBetween(30, 100),
            'renewal_text_unsuccessfully' => $this->faker->numberBetween(10, 50),
            'renewal_text_reminders' => $this->faker->numberBetween(50, 150),
            'renewal_text_reminder_eligible' => $this->faker->numberBetween(40, 120),
            'renewal_text_reminder_ineligible' => $this->faker->numberBetween(10, 30),
            'renewal_voice_notices' => $this->faker->numberBetween(80, 250),
            'renewal_voice_eligible' => $this->faker->numberBetween(60, 200),
            'renewal_voice_ineligible' => $this->faker->numberBetween(20, 50),
            'renewal_voice_reminders' => $this->faker->numberBetween(30, 100),
            'renewal_voice_reminder_eligible' => $this->faker->numberBetween(25, 80),
            'renewal_voice_reminder_ineligible' => $this->faker->numberBetween(5, 20),

            // Registration statistics
            'total_registered_users' => $this->faker->numberBetween(1000, 5000),
            'total_registered_barcodes' => $this->faker->numberBetween(1000, 5000),
            'total_registered_text' => $this->faker->numberBetween(600, 3000),
            'total_registered_voice' => $this->faker->numberBetween(400, 2000),
            'new_registrations_month' => $this->faker->numberBetween(10, 100),
            'new_voice_signups' => $this->faker->numberBetween(5, 50),
            'new_text_signups' => $this->faker->numberBetween(5, 50),

            // Voice call statistics
            'average_daily_calls' => $this->faker->numberBetween(50, 300),

            // Keyword usage
            'keyword_usage' => [
                'STOP' => $this->faker->numberBetween(5, 30),
                'START' => $this->faker->numberBetween(10, 50),
                'HELP' => $this->faker->numberBetween(2, 15),
                'INFO' => $this->faker->numberBetween(1, 10),
            ],

            // Timestamps
            'received_at' => $this->faker->dateTimeBetween($reportMonth, 'now'),
            'processed_at' => $this->faker->boolean(80) ? $this->faker->dateTimeBetween($reportMonth, 'now') : null,
        ];
    }

    /**
     * Indicate that the stat is unprocessed.
     */
    public function unprocessed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => null,
        ]);
    }

    /**
     * Indicate that the stat is processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => $this->faker->dateTimeBetween($attributes['received_at'], 'now'),
        ]);
    }

    /**
     * Set a specific month.
     */
    public function forMonth(int $year, int $month): static
    {
        $date = \Carbon\Carbon::create($year, $month, 1);

        return $this->state(fn (array $attributes) => [
            'report_month' => $date,
            'subject' => 'Shoutbomb Rpt - ' . $date->format('F Y'),
            'received_at' => $this->faker->dateTimeBetween($date, $date->copy()->endOfMonth()),
        ]);
    }

    /**
     * Set a specific branch.
     */
    public function forBranch(string $branchName): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_name' => $branchName,
        ]);
    }

    /**
     * Create stats with high activity.
     */
    public function highActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'hold_text_notices' => $this->faker->numberBetween(800, 1500),
            'hold_voice_notices' => $this->faker->numberBetween(400, 800),
            'overdue_text_notices' => $this->faker->numberBetween(1000, 2000),
            'overdue_voice_notices' => $this->faker->numberBetween(500, 1000),
            'renewal_text_notices' => $this->faker->numberBetween(600, 1200),
            'renewal_voice_notices' => $this->faker->numberBetween(300, 600),
            'total_registered_users' => $this->faker->numberBetween(8000, 15000),
            'average_daily_calls' => $this->faker->numberBetween(500, 1000),
        ]);
    }

    /**
     * Create stats with low activity.
     */
    public function lowActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'hold_text_notices' => $this->faker->numberBetween(20, 100),
            'hold_voice_notices' => $this->faker->numberBetween(10, 50),
            'overdue_text_notices' => $this->faker->numberBetween(50, 150),
            'overdue_voice_notices' => $this->faker->numberBetween(20, 80),
            'renewal_text_notices' => $this->faker->numberBetween(30, 100),
            'renewal_voice_notices' => $this->faker->numberBetween(15, 50),
            'total_registered_users' => $this->faker->numberBetween(300, 1000),
            'average_daily_calls' => $this->faker->numberBetween(10, 100),
        ]);
    }
}
