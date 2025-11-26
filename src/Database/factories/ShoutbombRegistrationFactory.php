<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\ShoutbombRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShoutbombRegistrationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ShoutbombRegistration::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Based on faker script distribution: 60% SMS, 20% Voice
        // But some patrons have both, so text subscribers will be higher
        $totalSubscribers = $this->faker->numberBetween(1000, 5000);
        $textPercentage = $this->faker->randomFloat(2, 55, 70); // 55-70%
        $voicePercentage = 100 - $textPercentage;

        $textSubscribers = (int) round($totalSubscribers * ($textPercentage / 100));
        $voiceSubscribers = (int) round($totalSubscribers * ($voicePercentage / 100));

        // Changes are typically small day-to-day
        $textChange = $this->faker->numberBetween(-20, 50);
        $voiceChange = $this->faker->numberBetween(-10, 20);

        // New registrations are typically positive
        $newRegistrations = $this->faker->numberBetween(0, 30);

        // Unsubscribes happen but less frequently
        $unsubscribes = $this->faker->numberBetween(0, 15);

        // Invalid numbers are rare
        $invalidNumbers = $this->faker->numberBetween(0, 10);

        return [
            'snapshot_date' => $this->faker->dateTimeBetween('-90 days', 'now'),
            'total_text_subscribers' => $textSubscribers,
            'total_voice_subscribers' => $voiceSubscribers,
            'total_subscribers' => $totalSubscribers,
            'text_percentage' => $textPercentage,
            'voice_percentage' => $voicePercentage,
            'text_change' => $textChange,
            'voice_change' => $voiceChange,
            'new_registrations' => $newRegistrations,
            'unsubscribes' => $unsubscribes,
            'invalid_numbers' => $invalidNumbers,
            'report_file' => 'shoutbomb_registration_' . $this->faker->date('Y-m-d') . '.csv',
            'report_type' => 'Weekly',
            'imported_at' => now(),
        ];
    }

    /**
     * Indicate that this is a growing snapshot (positive changes).
     *
     * @return Factory
     */
    public function growing()
    {
        return $this->state(function (array $attributes) {
            $newRegistrations = $this->faker->numberBetween(20, 50);
            $unsubscribes = $this->faker->numberBetween(0, 10);

            return [
                'text_change' => $this->faker->numberBetween(10, 50),
                'voice_change' => $this->faker->numberBetween(5, 20),
                'new_registrations' => $newRegistrations,
                'unsubscribes' => $unsubscribes,
            ];
        });
    }

    /**
     * Indicate that this is a declining snapshot (negative changes).
     *
     * @return Factory
     */
    public function declining()
    {
        return $this->state(function (array $attributes) {
            $newRegistrations = $this->faker->numberBetween(0, 10);
            $unsubscribes = $this->faker->numberBetween(15, 40);

            return [
                'text_change' => $this->faker->numberBetween(-30, -5),
                'voice_change' => $this->faker->numberBetween(-15, -2),
                'new_registrations' => $newRegistrations,
                'unsubscribes' => $unsubscribes,
            ];
        });
    }

    /**
     * Indicate that this is a stable snapshot (minimal changes).
     *
     * @return Factory
     */
    public function stable()
    {
        return $this->state(function (array $attributes) {
            return [
                'text_change' => $this->faker->numberBetween(-5, 5),
                'voice_change' => $this->faker->numberBetween(-3, 3),
                'new_registrations' => $this->faker->numberBetween(5, 15),
                'unsubscribes' => $this->faker->numberBetween(3, 12),
            ];
        });
    }

    /**
     * Indicate that text is the dominant subscription type.
     *
     * @return Factory
     */
    public function textDominant()
    {
        return $this->state(function (array $attributes) {
            $totalSubscribers = $attributes['total_subscribers'];
            $textPercentage = $this->faker->randomFloat(2, 70, 85);
            $voicePercentage = 100 - $textPercentage;

            return [
                'text_percentage' => $textPercentage,
                'voice_percentage' => $voicePercentage,
                'total_text_subscribers' => (int) round($totalSubscribers * ($textPercentage / 100)),
                'total_voice_subscribers' => (int) round($totalSubscribers * ($voicePercentage / 100)),
            ];
        });
    }

    /**
     * Indicate that voice is more prominent than usual.
     *
     * @return Factory
     */
    public function voiceProminent()
    {
        return $this->state(function (array $attributes) {
            $totalSubscribers = $attributes['total_subscribers'];
            $textPercentage = $this->faker->randomFloat(2, 40, 55);
            $voicePercentage = 100 - $textPercentage;

            return [
                'text_percentage' => $textPercentage,
                'voice_percentage' => $voicePercentage,
                'total_text_subscribers' => (int) round($totalSubscribers * ($textPercentage / 100)),
                'total_voice_subscribers' => (int) round($totalSubscribers * ($voicePercentage / 100)),
            ];
        });
    }
}
