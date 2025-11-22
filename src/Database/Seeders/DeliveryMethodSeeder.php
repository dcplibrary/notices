<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Seeder;
use Dcplibrary\Notices\Models\DeliveryMethod;

class DeliveryMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [ 'delivery_option_id' => 1, 'delivery_option' => 'Mailing Address', 'description' => 'Postal mail notification', 'display_order' => 1, 'enabled' => true ],
            [ 'delivery_option_id' => 2, 'delivery_option' => 'Email Address', 'description' => 'Email notification', 'display_order' => 2, 'enabled' => true ],
            [ 'delivery_option_id' => 3, 'delivery_option' => 'Phone 1', 'description' => 'Phone call notification (PhoneVoice1 - Primary voice)', 'display_order' => 3, 'enabled' => true ],
            [ 'delivery_option_id' => 4, 'delivery_option' => 'Phone 2', 'description' => 'Secondary phone - Not used at DCPL', 'display_order' => 4, 'enabled' => false ],
            [ 'delivery_option_id' => 5, 'delivery_option' => 'Phone 3', 'description' => 'Tertiary phone - Not used at DCPL', 'display_order' => 5, 'enabled' => false ],
            [ 'delivery_option_id' => 6, 'delivery_option' => 'FAX', 'description' => 'Fax notification - Not used at DCPL', 'display_order' => 6, 'enabled' => false ],
            [ 'delivery_option_id' => 7, 'delivery_option' => 'EDI', 'description' => 'Electronic Data Interchange - Not used at DCPL', 'display_order' => 7, 'enabled' => false ],
            [ 'delivery_option_id' => 8, 'delivery_option' => 'TXT Messaging', 'description' => 'Text message (SMS) notification (PhoneVoice1 - SMS)', 'display_order' => 8, 'enabled' => true ],
        ];

        foreach ($methods as $method) {
            DeliveryMethod::updateOrCreate(
                ['delivery_option_id' => $method['delivery_option_id']],
                $method
            );
        }
    }
}
