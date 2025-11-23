<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\NotificationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<NotificationSetting>
     */
    protected $model = NotificationSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => null,
            'scope_id' => null,
            'group' => 'notices',
            'key' => $this->faker->unique()->slug(2),
            'value' => 'test-value',
            'type' => 'string',
            'description' => $this->faker->sentence(),
            'is_public' => false,
            'is_editable' => true,
            'is_sensitive' => false,
            'validation_rules' => null,
            'updated_by' => 'factory',
        ];
    }

    /**
     * Indicate that the setting is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the setting is not editable.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_editable' => false,
        ]);
    }

    /**
     * Indicate that the setting is sensitive/encrypted.
     */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sensitive' => true,
            'type' => 'encrypted',
        ]);
    }
}
