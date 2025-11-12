<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Seeder;
use Dcplibrary\Notices\Models\PolarisPhoneNotice;

class PolarisPhoneNoticeSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Creating Polaris phone notice records...');

        PolarisPhoneNotice::factory()->count(100)->create();
        PolarisPhoneNotice::factory()->count(60)->voice()->create();
        PolarisPhoneNotice::factory()->count(40)->text()->create();
        PolarisPhoneNotice::factory()->count(30)->withEmail()->create();
        PolarisPhoneNotice::factory()->count(20)->withoutEmail()->create();

        $totalRecords = PolarisPhoneNotice::count();
        $this->command?->info("Created {$totalRecords} Polaris phone notice records.");

        $voiceCount = PolarisPhoneNotice::voice()->count();
        $textCount = PolarisPhoneNotice::text()->count();
        $this->command?->newLine();
        $this->command?->table(
            ['Delivery Type', 'Count'],
            [
                ['Voice', $voiceCount],
                ['Text', $textCount],
                ['Total', $totalRecords],
            ]
        );
        $this->command?->newLine();
        $this->command?->info('✅ Polaris phone notice seeding complete!');
    }
}
