<?php

namespace Dcplibrary\Notices\Database\Factories;

use Dcplibrary\Notices\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Notification factory.
 *
 * This exists primarily so the Notification model's HasFactory integration
 * does not fatal error in apps that load the model.
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'import_batch_id'          => null,
            'notification_key'         => $this->faker->uuid(),
            'export_filename'          => null,
            'patron_barcode'           => null,
            'patron_id'                => null,
            'organization_id'          => null,
            'delivery_option_id'       => null,
            'notification_type_id'     => null,
            'ingested_email_id'        => null,
            'notification_type'        => null,
            'notice_date'              => null,
            'schedule_step'            => null,
            'status'                   => 'pending',
            'is_combined'              => false,
            'failure_reason'           => null,
            'raw_payload'              => null,
            'search_patron_first_name' => null,
            'search_patron_last_name'  => null,
            'search_full_name'         => null,
            'search_title'             => null,
            'search_item_barcode'      => null,
            'generated_at'             => null,
            'first_exported_at'        => now(),
            'last_exported_at'         => null,
            'expected_delivery_at'     => null,
            'delivered_at'             => null,
            'failed_at'                => null,
        ];
    }
}
