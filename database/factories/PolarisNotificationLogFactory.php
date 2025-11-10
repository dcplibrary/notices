<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\PolarisNotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class PolarisNotificationLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PolarisNotificationLog::class;

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
            'PatronID' => $this->faker->numberBetween(10000, 20000),
            'PatronBarcode' => '23307' . $this->faker->numerify('########'),
            'NotificationDateTime' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'NotificationTypeID' => $notificationTypeId,
            'DeliveryOptionID' => $deliveryOptionId,
            'DeliveryString' => $deliveryString,
            'OverduesCount' => $notificationTypeId === 1 ? $this->faker->numberBetween(1, 7) : 0,
            'HoldsCount' => $notificationTypeId === 2 ? $this->faker->numberBetween(1, 3) : 0,
            'CancelsCount' => 0,
            'RecallsCount' => 0,
            'NotificationStatusID' => $statusId,
            'Details' => '',
            'RoutingsCount' => 0,
            'ReportingOrgID' => 3, // DCPL main from faker script
            'Reported' => 1,
            'Overdues2ndCount' => null,
            'Overdues3rdCount' => null,
            'BillsCount' => null,
            'LanguageID' => null,
            'CarrierName' => null,
            'ManualBillCount' => null,
        ];
    }

    /**
     * Indicate that the notification is for holds.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function holds()
    {
        return $this->state(function (array $attributes) {
            return [
                'NotificationTypeID' => 2,
                'HoldsCount' => $this->faker->numberBetween(1, 3),
                'OverduesCount' => 0,
            ];
        });
    }

    /**
     * Indicate that the notification is for overdues.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function overdues()
    {
        return $this->state(function (array $attributes) {
            return [
                'NotificationTypeID' => 1,
                'OverduesCount' => $this->faker->numberBetween(1, 7),
                'HoldsCount' => 0,
            ];
        });
    }

    /**
     * Indicate that the notification is for almost overdue items.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function almostOverdue()
    {
        return $this->state(function (array $attributes) {
            return [
                'NotificationTypeID' => 7,
                'OverduesCount' => 0,
                'HoldsCount' => 0,
            ];
        });
    }

    /**
     * Indicate that the notification was sent via email.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function email()
    {
        return $this->state(function (array $attributes) {
            return [
                'DeliveryOptionID' => 2,
                'DeliveryString' => $this->faker->safeEmail,
                'NotificationStatusID' => $this->faker->randomElement([12, 14]),
            ];
        });
    }

    /**
     * Indicate that the notification was sent via SMS.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function sms()
    {
        return $this->state(function (array $attributes) {
            return [
                'DeliveryOptionID' => 8,
                'DeliveryString' => '2705550' . $this->faker->numberBetween(100, 199),
                'NotificationStatusID' => 16,
            ];
        });
    }

    /**
     * Indicate that the notification was sent via voice call.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function voice()
    {
        return $this->state(function (array $attributes) {
            return [
                'DeliveryOptionID' => 3,
                'DeliveryString' => '2705550' . $this->faker->numberBetween(100, 199),
                'NotificationStatusID' => $this->faker->randomElement([1, 2]),
            ];
        });
    }

    /**
     * Indicate that the notification was sent via mail.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function mail()
    {
        return $this->state(function (array $attributes) {
            return [
                'DeliveryOptionID' => 1,
                'DeliveryString' => '',
                'NotificationStatusID' => 15,
            ];
        });
    }

    /**
     * Indicate that the notification is unreported.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function unreported()
    {
        return $this->state(function (array $attributes) {
            return [
                'Reported' => 0,
            ];
        });
    }
}
