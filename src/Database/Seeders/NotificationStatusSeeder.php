<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Seeder;
use Dcplibrary\Notices\Models\NotificationStatus;

class NotificationStatusSeeder extends Seeder
{
    public function run(): void
    {
        $completedStatuses = [1, 2, 12, 15, 16];
        $failedStatuses = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14];
        $statuses = [
            [1, 'Call completed - Voice'],
            [2, 'Call completed - Answering machine'],
            [3, 'Call not completed - Hang up'],
            [4, 'Call not completed - Busy'],
            [5, 'Call not completed - No answer'],
            [6, 'Call not completed - No ring'],
            [7, 'Call failed - No dial tone'],
            [8, 'Call failed - Intercept tones heard'],
            [9, 'Call failed - Probable bad phone number'],
            [10, 'Call failed - Maximum number of retries exceeded'],
            [11, 'Call failed - Undetermined error'],
            [12, 'Email Completed'],
            [13, 'Email Failed - Invalid address'],
            [14, 'Email Failed'],
            [15, 'Mail Printed'],
            [16, 'Sent'],
        ];
        $order = 0;
        foreach ($statuses as [$id, $description]) {
            $category = in_array($id, $completedStatuses) ? 'completed' : (in_array($id, $failedStatuses) ? 'failed' : 'pending');
            NotificationStatus::updateOrCreate(
                ['notification_status_id' => $id],
                [
                    'description' => $description,
                    'category' => $category,
                    'enabled' => true,
                    'display_order' => $order++,
                ]
            );
        }
    }
}
