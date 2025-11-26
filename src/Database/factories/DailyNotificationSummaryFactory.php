<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyNotificationSummaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DailyNotificationSummary::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Notification type IDs
        $notificationTypes = [1, 2, 7, 8]; // Overdue, Hold, Almost Overdue, Fine

        // Delivery option IDs
        $deliveryOptions = [1, 2, 3, 8]; // Mail, Email, Voice, SMS

        $notificationTypeId = $this->faker->randomElement($notificationTypes);
        $deliveryOptionId = $this->faker->randomElement($deliveryOptions);

        // Generate realistic numbers
        $totalSent = $this->faker->numberBetween(50, 500);
        $totalSuccess = (int) ($totalSent * $this->faker->randomFloat(2, 0.85, 0.98));
        $totalFailed = (int) ($totalSent * $this->faker->randomFloat(2, 0.01, 0.10));
        $totalPending = $totalSent - $totalSuccess - $totalFailed;

        // Calculate rates
        $successRate = $totalSent > 0 ? round(($totalSuccess / $totalSent) * 100, 2) : 0;
        $failureRate = $totalSent > 0 ? round(($totalFailed / $totalSent) * 100, 2) : 0;

        // Item counts based on notification type
        $totalHolds = $notificationTypeId === 2 ? $this->faker->numberBetween(50, 200) : 0;
        $totalOverdues = $notificationTypeId === 1 ? $this->faker->numberBetween(50, 300) : 0;
        $totalOverdues2nd = $notificationTypeId === 1 ? $this->faker->numberBetween(10, 50) : 0;
        $totalOverdues3rd = $notificationTypeId === 1 ? $this->faker->numberBetween(5, 20) : 0;
        $totalBills = $notificationTypeId === 8 ? $this->faker->numberBetween(10, 50) : 0;

        return [
            'summary_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'notification_type_id' => $notificationTypeId,
            'delivery_option_id' => $deliveryOptionId,
            'total_sent' => $totalSent,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'total_pending' => $totalPending,
            'total_holds' => $totalHolds,
            'total_overdues' => $totalOverdues,
            'total_overdues_2nd' => $totalOverdues2nd,
            'total_overdues_3rd' => $totalOverdues3rd,
            'total_cancels' => $this->faker->numberBetween(0, 10),
            'total_recalls' => $this->faker->numberBetween(0, 5),
            'total_bills' => $totalBills,
            'unique_patrons' => $this->faker->numberBetween(40, $totalSent),
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
            'aggregated_at' => now(),
        ];
    }

    /**
     * Indicate that this summary is for hold notifications.
     *
     * @return Factory
     */
    public function holds()
    {
        return $this->state(function (array $attributes) {
            $totalHolds = $this->faker->numberBetween(50, 200);

            return [
                'notification_type_id' => 2,
                'total_holds' => $totalHolds,
                'total_overdues' => 0,
                'total_overdues_2nd' => 0,
                'total_overdues_3rd' => 0,
                'total_bills' => 0,
            ];
        });
    }

    /**
     * Indicate that this summary is for overdue notifications.
     *
     * @return Factory
     */
    public function overdues()
    {
        return $this->state(function (array $attributes) {
            $totalOverdues = $this->faker->numberBetween(50, 300);
            $totalOverdues2nd = $this->faker->numberBetween(10, 50);
            $totalOverdues3rd = $this->faker->numberBetween(5, 20);

            return [
                'notification_type_id' => 1,
                'total_holds' => 0,
                'total_overdues' => $totalOverdues,
                'total_overdues_2nd' => $totalOverdues2nd,
                'total_overdues_3rd' => $totalOverdues3rd,
                'total_bills' => 0,
            ];
        });
    }

    /**
     * Indicate that this summary is for email delivery.
     *
     * @return Factory
     */
    public function email()
    {
        return $this->state(function (array $attributes) {
            // Email typically has high success rates
            $totalSent = $attributes['total_sent'];
            $successRate = $this->faker->randomFloat(2, 0.90, 0.98);
            $totalSuccess = (int) ($totalSent * $successRate);
            $totalFailed = (int) ($totalSent * (1 - $successRate));

            return [
                'delivery_option_id' => 2,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'total_pending' => 0,
                'success_rate' => round($successRate * 100, 2),
                'failure_rate' => round((1 - $successRate) * 100, 2),
            ];
        });
    }

    /**
     * Indicate that this summary is for SMS delivery.
     *
     * @return Factory
     */
    public function sms()
    {
        return $this->state(function (array $attributes) {
            // SMS typically has very high success rates
            $totalSent = $attributes['total_sent'];
            $successRate = $this->faker->randomFloat(2, 0.92, 0.99);
            $totalSuccess = (int) ($totalSent * $successRate);
            $totalFailed = (int) ($totalSent * (1 - $successRate));

            return [
                'delivery_option_id' => 8,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'total_pending' => 0,
                'success_rate' => round($successRate * 100, 2),
                'failure_rate' => round((1 - $successRate) * 100, 2),
            ];
        });
    }

    /**
     * Indicate that this summary is for voice delivery.
     *
     * @return Factory
     */
    public function voice()
    {
        return $this->state(function (array $attributes) {
            // Voice has lower success rates (voicemail, no answer, etc.)
            $totalSent = $attributes['total_sent'];
            $successRate = $this->faker->randomFloat(2, 0.70, 0.85);
            $totalSuccess = (int) ($totalSent * $successRate);
            $totalFailed = (int) ($totalSent * (1 - $successRate));

            return [
                'delivery_option_id' => 3,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'total_pending' => 0,
                'success_rate' => round($successRate * 100, 2),
                'failure_rate' => round((1 - $successRate) * 100, 2),
            ];
        });
    }

    /**
     * Indicate high success rate.
     *
     * @return Factory
     */
    public function highSuccess()
    {
        return $this->state(function (array $attributes) {
            $totalSent = $attributes['total_sent'];
            $successRate = 0.95;
            $totalSuccess = (int) ($totalSent * $successRate);
            $totalFailed = (int) ($totalSent * 0.05);

            return [
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'total_pending' => 0,
                'success_rate' => 95.00,
                'failure_rate' => 5.00,
            ];
        });
    }

    /**
     * Indicate low success rate.
     *
     * @return Factory
     */
    public function lowSuccess()
    {
        return $this->state(function (array $attributes) {
            $totalSent = $attributes['total_sent'];
            $successRate = 0.70;
            $totalSuccess = (int) ($totalSent * $successRate);
            $totalFailed = (int) ($totalSent * 0.30);

            return [
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'total_pending' => 0,
                'success_rate' => 70.00,
                'failure_rate' => 30.00,
            ];
        });
    }
}
