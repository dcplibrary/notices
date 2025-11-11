<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Seeder;
use Dcplibrary\Notices\Models\NotificationStatus;

class NotificationStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Populates notification_statuses from Polaris.NotificationStatuses
     * Source: /polaris-databases/sql/Polaris.Polaris.NotificationStatuses.csv
     */
    public function run(): void
    {
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

        foreach ($statuses as [$id, $description]) {
            NotificationStatus::updateOrCreate(
                ['notification_status_id' => $id],
                ['description' => $description]
            );
        }
    }
}
