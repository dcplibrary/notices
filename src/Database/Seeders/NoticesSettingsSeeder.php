<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Dcplibrary\Notices\Models\NotificationSetting;
use Illuminate\Database\Seeder;

class NoticesSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'shoutbomb', 'key' => 'enabled', 'value' => config('notices.shoutbomb.enabled', true), 'type' => 'boolean', 'description' => 'Enable Shoutbomb voice/SMS notifications', 'is_public' => true, 'is_editable' => true, 'is_sensitive' => false],
            ['group' => 'import', 'key' => 'default_days', 'value' => config('notices.import.default_days', 1), 'type' => 'integer', 'description' => 'Default number of days to import', 'is_public' => true, 'is_editable' => true, 'is_sensitive' => false, 'validation_rules' => ['required', 'integer', 'min:1', 'max:365']],
        ];

        foreach ($settings as $s) {
            $setting = NotificationSetting::global()
                ->where('group', $s['group'])
                ->where('key', $s['key'])
                ->first();

            if (!$setting) {
                $setting = new NotificationSetting();
                $setting->group = $s['group'];
                $setting->key = $s['key'];
                $setting->type = $s['type'];
                $setting->description = $s['description'];
                $setting->is_public = $s['is_public'];
                $setting->is_editable = $s['is_editable'];
                $setting->is_sensitive = $s['is_sensitive'];
                if (isset($s['validation_rules'])) $setting->validation_rules = $s['validation_rules'];
                $setting->setTypedValue($s['value']);
                $setting->save();
            }
        }
    }
}
