<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = NotificationLog::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Notification type IDs from faker script
        $notificationTypes = [
            1 => 'Overdue 1st',
            2 => 'Hold',
            7 => 'Almost Overdue/Courtesy',
            8 => 'Fine',
        ];

        // Delivery option IDs
        $deliveryOptions = [
            1 => 'Mail',
            2 => 'Email',
            3 => 'Voice',
            8 => 'SMS',
        ];

        // Notification status IDs from faker script
        $notificationStatuses = [
            1 => 'Call - Voice',
            2 => 'Call - Machine',
            12 => 'Email Success',
            14 => 'Email Failed',
            15 => 'Mail Printed',
            16 => 'SMS Sent',
        ];

        $notificationTypeId = array_rand($notificationTypes);
        $deliveryOptionId = array_rand($deliveryOptions);

        // Pick appropriate status based on delivery option
        $statusId = match($deliveryOptionId) {
            2 => $this->faker->randomElement([12, 14]), // Email: Success or Failed
            8 => 16, // SMS: Sent
            3 => $this->faker->randomElement([1, 2]), // Voice: Voice or Machine
            1 => 15, // Mail: Printed
            default => 12,
        };

        // Generate delivery string based on delivery option
        $deliveryString = match($deliveryOptionId) {
            2 => $this->faker->safeEmail, // Email
            3, 8 => '2705550' . $this->faker->numberBetween(100, 199), // Phone for Voice/SMS
            default => '', // Mail has no delivery string
        };

        return [
            // Ensure uniqueness against existing DB rows as well as within this run
            'polaris_log_id' => $this->generateUniquePolarisLogId(),
            'patron_id' => $this->faker->numberBetween(10000, 20000),
            'patron_barcode' => '23307' . $this->faker->numerify('########'),
            'notification_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'notification_type_id' => $notificationTypeId,
            'delivery_option_id' => $deliveryOptionId,
            'notification_status_id' => $statusId,
            'delivery_string' => $deliveryString,
            'holds_count' => $notificationTypeId === 2 ? $this->faker->numberBetween(1, 3) : 0,
            'overdues_count' => $notificationTypeId === 1 ? $this->faker->numberBetween(1, 7) : 0,
            'overdues_2nd_count' => $notificationTypeId === 1 ? $this->faker->numberBetween(0, 2) : 0,
            'overdues_3rd_count' => $notificationTypeId === 1 ? $this->faker->numberBetween(0, 1) : 0,
            'cancels_count' => 0,
            'recalls_count' => 0,
            'routings_count' => 0,
            'bills_count' => $notificationTypeId === 8 ? $this->faker->numberBetween(1, 5) : 0,
            'manual_bill_count' => 0,
            'reporting_org_id' => 3, // DCPL main from faker script
            'language_id' => null,
            'carrier_name' => null,
            'details' => '',
            'reported' => true,
            'imported_at' => now(),
        ];
    }

    /**
     * Generate a polaris_log_id that is unique across the database.
     */
    protected function generateUniquePolarisLogId(): int
    {
        do {
            $id = $this->faker->numberBetween(100000, 999999);
        } while (NotificationLog::where('polaris_log_id', $id)->exists());

        return $id;
    }

    /**
     * Indicate that the notification is for holds.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function holds()
    {
        return $this->state(function (array $attributes) {
            return [
                'notification_type_id' => 2,
                'holds_count' => $this->faker->numberBetween(1, 3),
                'overdues_count' => 0,
                'overdues_2nd_count' => 0,
                'overdues_3rd_count' => 0,
                'bills_count' => 0,
            ];
        });
    }

    /**
     * Indicate that the notification is for overdues.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function overdues()
    {
        return $this->state(function (array $attributes) {
            return [
                'notification_type_id' => 1,
                'holds_count' => 0,
                'overdues_count' => $this->faker->numberBetween(1, 7),
                'overdues_2nd_count' => $this->faker->numberBetween(0, 2),
                'overdues_3rd_count' => $this->faker->numberBetween(0, 1),
                'bills_count' => 0,
            ];
        });
    }

    /**
     * Indicate that the notification is for almost overdue items.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function almostOverdue()
    {
        return $this->state(function (array $attributes) {
            return [
                'notification_type_id' => 7,
                'holds_count' => 0,
                'overdues_count' => 0,
                'overdues_2nd_count' => 0,
                'overdues_3rd_count' => 0,
                'bills_count' => 0,
            ];
        });
    }

    /**
     * Indicate that the notification was sent via email.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function email()
    {
        return $this->state(function (array $attributes) {
            return [
                'delivery_option_id' => 2,
                'delivery_string' => $this->faker->safeEmail,
                'notification_status_id' => $this->faker->randomElement([12, 14]),
            ];
        });
    }

    /**
     * Indicate that the notification was sent via SMS.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function sms()
    {
        return $this->state(function (array $attributes) {
            return [
                'delivery_option_id' => 8,
                'delivery_string' => '2705550' . $this->faker->numberBetween(100, 199),
                'notification_status_id' => 16,
            ];
        });
    }

    /**
     * Indicate that the notification was sent via voice call.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function voice()
    {
        return $this->state(function (array $attributes) {
            return [
                'delivery_option_id' => 3,
                'delivery_string' => '2705550' . $this->faker->numberBetween(100, 199),
                'notification_status_id' => $this->faker->randomElement([1, 2]),
            ];
        });
    }

    /**
     * Indicate that the notification was sent via mail.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function mail()
    {
        return $this->state(function (array $attributes) {
            return [
                'delivery_option_id' => 1,
                'delivery_string' => '',
                'notification_status_id' => 15,
            ];
        });
    }

    /**
     * Indicate that the notification is successful.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function successful()
    {
        return $this->state(function (array $attributes) {
            return [
                'notification_status_id' => 12, // Email Success
            ];
        });
    }

    /**
     * Indicate that the notification failed.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function failed()
    {
        return $this->state(function (array $attributes) {
            return [
                'notification_status_id' => 14, // Email Failed
            ];
        });
    }

    /**
     * Indicate that the notification is unreported.
     *
     * @return \\Illuminate\\Database\\Eloquent\\Factories\\Factory
     */
    public function unreported()
    {
        return $this->state(function (array $attributes) {
            return [
                'reported' => false,
            ];
        });
    }
}
