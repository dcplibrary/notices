<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Dcplibrary\Notices\Models\NotificationLog;
use Illuminate\Database\Seeder;

class NoticeDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create 50 random notifications
        NotificationLog::factory()->count(50)->create();

        // Create specific types
        NotificationLog::factory()->count(20)->holds()->create();
        NotificationLog::factory()->count(15)->overdues()->create();
        NotificationLog::factory()->count(10)->almostOverdue()->create();

        // Create by delivery method
        NotificationLog::factory()->count(30)->email()->create();
        NotificationLog::factory()->count(25)->voice()->create();
        NotificationLog::factory()->count(20)->sms()->create();
        NotificationLog::factory()->count(15)->mail()->create();

        // Create successful/failed notifications
        NotificationLog::factory()->count(40)->successful()->create();
        NotificationLog::factory()->count(10)->failed()->create();
    }
}