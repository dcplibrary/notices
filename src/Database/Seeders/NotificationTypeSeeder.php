<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Seeder;

class NotificationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $disabledTypes = [4, 6, 14, 15, 16];
        $types = [
            [0, 'Combined'],
            [1, '1st Overdue'],
            [2, 'Hold'],
            [3, 'Cancel'],
            [4, 'Recall'],
            [5, 'All'],
            [6, 'Route'],
            [7, 'Almost overdue/Auto-renew reminder'],
            [8, 'Fine'],
            [9, 'Inactive Reminder'],
            [10, 'Expiration Reminder'],
            [11, 'Bill'],
            [12, '2nd Overdue'],
            [13, '3rd Overdue'],
            [14, 'Serial Claim'],
            [15, 'Polaris Fusion Access Request Responses'],
            [16, 'Course Reserves'],
            [17, 'Borrow-By-Mail Failure Notice'],
            [18, '2nd Hold'],
            [19, 'Missing Part'],
            [20, 'Manual Bill'],
            [21, '2nd Fine Notice'],
        ];
        $order = 0;
        foreach ($types as [$id, $description]) {
            NotificationType::updateOrCreate(
                ['notification_type_id' => $id],
                [
                    'description' => $description,
                    'enabled' => !in_array($id, $disabledTypes),
                    'display_order' => $order++,
                ]
            );
        }
    }
}
