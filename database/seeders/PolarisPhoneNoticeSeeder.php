<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Illuminate\Database\Seeder;

/**
 * PolarisPhoneNoticeSeeder
 * 
 * Seeds the polaris_phone_notices table with test data.
 * This table represents Polaris PhoneNotices.csv exports used for
 * verification of notices sent to Shoutbomb.
 */
class PolarisPhoneNoticeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating Polaris phone notice records...');
        
        // Create 100 random phone notices
        PolarisPhoneNotice::factory()->count(100)->create();

        // Create specific delivery type splits
        PolarisPhoneNotice::factory()->count(60)->voice()->create();
        PolarisPhoneNotice::factory()->count(40)->text()->create();

        // Create some with and without email
        PolarisPhoneNotice::factory()->count(30)->withEmail()->create();
        PolarisPhoneNotice::factory()->count(20)->withoutEmail()->create();

        $totalRecords = PolarisPhoneNotice::count();
        $this->command->info("Created {$totalRecords} Polaris phone notice records.");
        
        // Show breakdown
        $voiceCount = PolarisPhoneNotice::voice()->count();
        $textCount = PolarisPhoneNotice::text()->count();
        
        $this->command->newLine();
        $this->command->table(
            ['Delivery Type', 'Count'],
            [
                ['Voice', $voiceCount],
                ['Text', $textCount],
                ['Total', $totalRecords],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('✅ Polaris phone notice seeding complete!');
    }
}
