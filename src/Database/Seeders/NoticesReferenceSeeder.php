<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Seeder;

class NoticesReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DeliveryMethodSeeder::class,
            NotificationTypeSeeder::class,
            NotificationStatusSeeder::class,
            PopulateReferenceDataLabelsSeeder::class,
            NoticesSettingsSeeder::class,
        ]);
    }
}
