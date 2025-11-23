<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\ShoutbombKeywordUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShoutbombKeywordUsageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ShoutbombKeywordUsage::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Common Shoutbomb keywords
        $keywords = [
            'HOLDS' => 'Check hold status',
            'RENEW' => 'Renew items',
            'CHECKOUTS' => 'Check items out',
            'FINES' => 'Check fines',
            'HELP' => 'Get help',
            'STOP' => 'Unsubscribe',
            'START' => 'Resubscribe',
            'STATUS' => 'Check account status',
        ];

        $keyword = $this->faker->randomElement(array_keys($keywords));
        $description = $keywords[$keyword];

        return [
            'keyword' => $keyword,
            'patron_barcode' => '23307' . $this->faker->numerify('########'),
            'phone_number' => '2705550' . $this->faker->numberBetween(100, 199),
            'usage_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'keyword_description' => $description,
            'usage_count' => $this->faker->numberBetween(1, 10),
            'report_file' => 'shoutbomb_keyword_' . $this->faker->date('Y-m-d') . '.csv',
            'report_period' => $this->faker->randomElement(['Weekly', 'Monthly']),
            'imported_at' => now(),
        ];
    }

    /**
     * Indicate that the keyword is HOLDS.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function holds()
    {
        return $this->state(function (array $attributes) {
            return [
                'keyword' => 'HOLDS',
                'keyword_description' => 'Check hold status',
            ];
        });
    }

    /**
     * Indicate that the keyword is RENEW.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function renew()
    {
        return $this->state(function (array $attributes) {
            return [
                'keyword' => 'RENEW',
                'keyword_description' => 'Renew items',
            ];
        });
    }

    /**
     * Indicate that the keyword is CHECKOUTS.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function checkouts()
    {
        return $this->state(function (array $attributes) {
            return [
                'keyword' => 'CHECKOUTS',
                'keyword_description' => 'Check items out',
            ];
        });
    }

    /**
     * Indicate that the keyword is FINES.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function fines()
    {
        return $this->state(function (array $attributes) {
            return [
                'keyword' => 'FINES',
                'keyword_description' => 'Check fines',
            ];
        });
    }

    /**
     * Indicate that the keyword is HELP.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function help()
    {
        return $this->state(function (array $attributes) {
            return [
                'keyword' => 'HELP',
                'keyword_description' => 'Get help',
            ];
        });
    }

    /**
     * Indicate that the keyword is STOP (unsubscribe).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function stop()
    {
        return $this->state(function (array $attributes) {
            return [
                'keyword' => 'STOP',
                'keyword_description' => 'Unsubscribe',
            ];
        });
    }

    /**
     * Indicate that the keyword is START (resubscribe).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function start()
    {
        return $this->state(function (array $attributes) {
            return [
                'keyword' => 'START',
                'keyword_description' => 'Resubscribe',
            ];
        });
    }

    /**
     * Indicate high usage count.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function highUsage()
    {
        return $this->state(function (array $attributes) {
            return [
                'usage_count' => $this->faker->numberBetween(10, 50),
            ];
        });
    }

    /**
     * Indicate low usage count.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function lowUsage()
    {
        return $this->state(function (array $attributes) {
            return [
                'usage_count' => $this->faker->numberBetween(1, 3),
            ];
        });
    }
}
