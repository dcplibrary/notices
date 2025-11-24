<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_settings')) {
            return; // table not created yet; skip
        }

        $exists = DB::table('notification_settings')
            ->whereNull('scope')
            ->where('group', 'integrations')
            ->where('key', 'shoutbomb_reports.enabled')
            ->exists();

        if (!$exists) {
            DB::table('notification_settings')->insert([
                'scope' => null,
                'scope_id' => null,
                'group' => 'integrations',
                'key' => 'shoutbomb_reports.enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Enable using dcplibrary/shoutbomb-reports data to infer delivery failures (absence implies success when submitted).',
                'is_public' => true,
                'is_editable' => true,
                'is_sensitive' => false,
                'validation_rules' => json_encode(['boolean']),
                'updated_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_settings')) {
            return;
        }

        DB::table('notification_settings')
            ->whereNull('scope')
            ->where('group', 'integrations')
            ->where('key', 'shoutbomb_reports.enabled')
            ->delete();
    }
};