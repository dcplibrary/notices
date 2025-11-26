<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\NoticeFailureReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoticeFailureReport>
 */
class NoticeFailureReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = NoticeFailureReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $failureTypes = ['opted-out', 'invalid', 'voice-not-delivered', 'invalid-barcode-removed'];
        $noticeTypes = ['SMS', 'Voice'];
        $accountStatuses = ['active', 'deleted', 'unavailable'];

        $failureType = $this->faker->randomElement($failureTypes);
        $noticeType = $this->faker->randomElement($noticeTypes);

        return [
            'outlook_message_id' => $this->faker->unique()->uuid(),
            'subject' => $this->generateSubject($failureType, $noticeType),
            'from_address' => 'reports@shoutbomb.com',
            'patron_phone' => $this->faker->phoneNumber(),
            'patron_id' => $this->faker->numerify('######'),
            'patron_barcode' => $this->faker->numerify('##############'),
            'barcode_partial' => false,
            'patron_name' => $this->faker->name(),
            'notice_type' => $noticeType,
            'failure_type' => $failureType,
            'failure_reason' => $this->generateFailureReason($failureType),
            'account_status' => $this->faker->randomElement($accountStatuses),
            'notice_description' => $noticeType === 'Voice' ? 'Hold Ready for Pickup' : null,
            'attempt_count' => $this->faker->numberBetween(1, 5),
            'received_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'processed_at' => $this->faker->boolean(70) ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'raw_content' => null, // Usually null unless debugging
        ];
    }

    /**
     * Generate a realistic subject line based on failure type.
     */
    private function generateSubject(string $failureType, string $noticeType): string
    {
        return match ($failureType) {
            'opted-out' => "Shoutbomb Daily {$noticeType} Opt-Outs",
            'invalid' => "Shoutbomb Daily {$noticeType} Invalid Phone Numbers",
            'voice-not-delivered' => 'Shoutbomb Daily Voice Messages Not Delivered',
            'invalid-barcode-removed' => 'Shoutbomb Monthly Comprehensive Report',
            default => "Shoutbomb Daily {$noticeType} Failures",
        };
    }

    /**
     * Generate a realistic failure reason.
     */
    private function generateFailureReason(string $failureType): string
    {
        return match ($failureType) {
            'opted-out' => 'Patron sent STOP keyword',
            'invalid' => 'Invalid phone number format',
            'voice-not-delivered' => 'Voice call failed to connect',
            'invalid-barcode-removed' => 'Barcode redacted in monthly report (XXXXXXXXXX' . $this->faker->numerify('####') . ')',
            default => 'Unknown failure reason',
        };
    }

    /**
     * Indicate that the report is unprocessed.
     */
    public function unprocessed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => null,
        ]);
    }

    /**
     * Indicate that the report is processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => $this->faker->dateTimeBetween($attributes['received_at'], 'now'),
        ]);
    }

    /**
     * Indicate that the patron opted out.
     */
    public function optedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'failure_type' => 'opted-out',
            'failure_reason' => 'Patron sent STOP keyword',
            'subject' => 'Shoutbomb Daily ' . $attributes['notice_type'] . ' Opt-Outs',
        ]);
    }

    /**
     * Indicate that the phone number is invalid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'failure_type' => 'invalid',
            'failure_reason' => 'Invalid phone number format',
            'subject' => 'Shoutbomb Daily ' . $attributes['notice_type'] . ' Invalid Phone Numbers',
        ]);
    }

    /**
     * Indicate that this is a voice failure.
     */
    public function voiceNotDelivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'notice_type' => 'Voice',
            'failure_type' => 'voice-not-delivered',
            'failure_reason' => 'Voice call failed to connect',
            'notice_description' => $this->faker->randomElement([
                'Hold Ready for Pickup',
                'Overdue Notice',
                'Bill Notice',
            ]),
            'subject' => 'Shoutbomb Daily Voice Messages Not Delivered',
        ]);
    }

    /**
     * Indicate that the barcode is partial (redacted).
     */
    public function partialBarcode(): static
    {
        $lastFour = $this->faker->numerify('####');

        return $this->state(fn (array $attributes) => [
            'barcode_partial' => true,
            'patron_barcode' => 'XXXXXXXXXX' . $lastFour,
            'failure_type' => 'invalid-barcode-removed',
            'failure_reason' => "Barcode redacted in monthly report (XXXXXXXXXX{$lastFour})",
            'subject' => 'Shoutbomb Monthly Comprehensive Report',
        ]);
    }

    /**
     * Indicate that the account is deleted.
     */
    public function accountDeleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'deleted',
        ]);
    }

    /**
     * Indicate that the account is unavailable.
     */
    public function accountUnavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'unavailable',
        ]);
    }

    /**
     * Indicate that this is an SMS failure.
     */
    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'notice_type' => 'SMS',
            'notice_description' => null,
        ]);
    }

    /**
     * Indicate that this is a Voice failure.
     */
    public function voice(): static
    {
        return $this->state(fn (array $attributes) => [
            'notice_type' => 'Voice',
            'notice_description' => $this->faker->randomElement([
                'Hold Ready for Pickup',
                'Overdue Notice',
                'Bill Notice',
            ]),
        ]);
    }
}
