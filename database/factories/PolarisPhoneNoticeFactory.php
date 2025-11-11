<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PolarisPhoneNoticeFactory extends Factory
{
    protected $model = PolarisPhoneNotice::class;

    /**
     * Define the model's default state.
     * 
     * Note: PolarisPhoneNotice represents data from Polaris PhoneNotices.csv,
     * which contains phone/SMS notifications only (not email notifications).
     * The phone_number field should always contain a valid phone number.
     */
    public function definition(): array
    {
        $deliveryType = $this->faker->randomElement(['V', 'T']); // Voice or Text
        
        // Library codes and names
        $libraries = [
            ['code' => 'DCPL', 'name' => 'Daviess County Public Library'],
            ['code' => 'OWB', 'name' => 'Owensboro Branch'],
            ['code' => 'WHT', 'name' => 'Whitesville Branch'],
        ];
        
        $library = $this->faker->randomElement($libraries);
        
        return [
            'delivery_type' => $deliveryType === 'V' ? 'voice' : 'text',
            'language' => 'eng',
            'patron_barcode' => '23307' . $this->faker->numerify('########'),
            'first_name' => strtoupper($this->faker->firstName()),
            'last_name' => strtoupper($this->faker->lastName()),
            'phone_number' => '270' . $this->faker->numerify('#######'), // Always a phone number, never email
            'email' => $this->faker->optional(0.7)->safeEmail(),
            'library_code' => $library['code'],
            'library_name' => $library['name'],
            'item_barcode' => '33307' . $this->faker->numerify('########'),
            'notice_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'title' => $this->faker->sentence(rand(3, 8)),
            'organization_code' => '3',
            'language_code' => '1033',
            'patron_id' => $this->faker->numberBetween(10000, 150000),
            'item_record_id' => $this->faker->numberBetween(100000, 900000),
            'bib_record_id' => $this->faker->numberBetween(850000, 900000),
            'source_file' => 'PhoneNotices.csv',
            'imported_at' => now(),
        ];
    }

    /**
     * Indicate that the notice is for voice delivery.
     */
    public function voice(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_type' => 'voice',
        ]);
    }

    /**
     * Indicate that the notice is for text delivery.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_type' => 'text',
        ]);
    }

    /**
     * Indicate that the patron has an email address.
     */
    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $this->faker->safeEmail(),
        ]);
    }

    /**
     * Indicate that the patron has no email address.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }
}
