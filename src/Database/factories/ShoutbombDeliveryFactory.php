<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShoutbombDeliveryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ShoutbombDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $deliveryType = $this->faker->randomElement(['SMS', 'Voice']);
        $status = $this->faker->randomElement(['Delivered', 'Failed', 'Invalid', 'OptedOut']);

        // Notification types from the faker script
        $messageTypes = [
            'Hold Available',
            'Overdue Notice',
            'Almost Overdue',
            'Fine Notice',
            'General Notice',
        ];

        // Carriers for US numbers
        $carriers = [
            'AT&T',
            'Verizon',
            'T-Mobile',
            'Sprint',
            null, // Some might not have carrier info
        ];

        // Failure reasons (only if status is Failed)
        $failureReasons = [
            'Invalid Number',
            'No Answer',
            'Busy',
            'Network Error',
            'Carrier Rejected',
        ];

        return [
            'patron_barcode' => '23307' . $this->faker->numerify('########'),
            'patron_id' => $this->faker->optional(0.6)->numberBetween(100000, 999999),
            'patron_name' => $this->faker->optional(0.6)->name(),
            'library_name' => $this->faker->optional(0.5)->randomElement(['Main Library', 'East Branch', 'West Branch', 'South Branch']),
            'phone_number' => '2705550' . $this->faker->numberBetween(100, 199),
            'delivery_type' => $deliveryType,
            'message_type' => $this->faker->randomElement($messageTypes),
            'sent_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'status' => $status,
            'status_code' => $status === 'OptedOut' ? 326 : $this->faker->optional(0.3)->numberBetween(100, 999),
            'carrier' => $this->faker->randomElement($carriers),
            'failure_reason' => $status === 'Failed' ? $this->faker->randomElement($failureReasons) : ($status === 'Invalid' ? 'Invalid Number' : null),
            'report_file' => 'shoutbomb_delivery_' . $this->faker->date('Y-m-d') . '.csv',
            'report_type' => $deliveryType === 'SMS' ? 'text_delivery' : 'voice_delivery',
            'imported_at' => now(),
        ];
    }

    /**
     * Indicate that the delivery was successful.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function delivered()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'Delivered',
                'failure_reason' => null,
            ];
        });
    }

    /**
     * Indicate that the delivery failed.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function failed()
    {
        return $this->state(function (array $attributes) {
            $failureReasons = [
                'Invalid Number',
                'No Answer',
                'Busy',
                'Network Error',
                'Carrier Rejected',
            ];

            return [
                'status' => 'Failed',
                'failure_reason' => $this->faker->randomElement($failureReasons),
            ];
        });
    }

    /**
     * Indicate that the phone number was invalid.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function invalid()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'Invalid',
                'failure_reason' => 'Invalid Number',
                'report_type' => 'email_invalid',
            ];
        });
    }

    /**
     * Indicate that the patron opted out (from email reports).
     */
    public function optedOut()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'OptedOut',
                'status_code' => 326,
                'report_type' => 'email_optout',
            ];
        });
    }

    /**
     * Indicate undelivered voice (from email reports).
     */
    public function emailUndeliveredVoice()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'Failed',
                'report_type' => 'email_undelivered_voice',
            ];
        });
    }

    /**
     * Indicate that the delivery was SMS.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function sms()
    {
        return $this->state(function (array $attributes) {
            return [
                'delivery_type' => 'SMS',
                'report_type' => 'text_delivery',
            ];
        });
    }

    /**
     * Indicate that the delivery was Voice.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function voice()
    {
        return $this->state(function (array $attributes) {
            return [
                'delivery_type' => 'Voice',
                'report_type' => 'voice_delivery',
            ];
        });
    }

    /**
     * Indicate that the message was a hold notification.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function holdNotice()
    {
        return $this->state(function (array $attributes) {
            return [
                'message_type' => 'Hold Available',
            ];
        });
    }

    /**
     * Indicate that the message was an overdue notification.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function overdueNotice()
    {
        return $this->state(function (array $attributes) {
            return [
                'message_type' => 'Overdue Notice',
            ];
        });
    }
}
