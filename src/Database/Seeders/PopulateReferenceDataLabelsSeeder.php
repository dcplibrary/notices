<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Dcplibrary\Notices\Models\DeliveryMethod;
use Illuminate\Database\Seeder;

class PopulateReferenceDataLabelsSeeder extends Seeder
{
    public function run(): void
    {
        $typeLabels = [
            1 => '1st Overdue',
            2 => 'Hold Ready',
            3 => 'Hold Cancelled',
            7 => 'Almost Overdue',
            8 => 'Fine Notice',
            9 => 'Inactive Account',
            10 => 'Card Expiring',
            11 => 'Bill Notice',
            12 => '2nd Overdue',
            13 => '3rd Overdue',
            17 => 'Borrow-By-Mail',
            18 => '2nd Hold Notice',
            20 => 'Manual Bill',
            21 => '2nd Fine Notice',
        ];
        foreach ($typeLabels as $id => $label) {
            NotificationType::where('notification_type_id', $id)->update(['label' => $label]);
        }

        $deliveryLabels = [
            1 => 'Mail',
            2 => 'Email',
            3 => 'Voice Call',
            4 => 'Voice Call (Alt)',
            5 => 'Voice Call (Alt 2)',
            6 => 'Fax',
            7 => 'EDI',
            8 => 'Text Message',
            9 => 'Mobile App',
        ];
        foreach ($deliveryLabels as $id => $label) {
            DeliveryMethod::where('delivery_option_id', $id)->update(['label' => $label]);
        }

        $statusLabels = [
            1 => 'Answered',
            2 => 'Voicemail',
            3 => 'Hung Up',
            4 => 'Busy',
            5 => 'No Answer',
            6 => 'Answering Machine',
            7 => 'Invalid Number',
            8 => 'Circuit Busy',
            9 => 'Unallocated Number',
            10 => 'Line Out of Order',
            11 => 'Incompatible Destination',
            12 => 'Email Completed',
            13 => 'Email Failed',
            14 => 'Email Bounced',
            15 => 'Text Sent',
            16 => 'Mail Printed',
        ];
        foreach ($statusLabels as $id => $label) {
            NotificationStatus::where('notification_status_id', $id)->update(['label' => $label]);
        }

        $this->command?->info('Reference data labels populated successfully!');
    }
}
