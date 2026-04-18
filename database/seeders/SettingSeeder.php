<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'site_name', 'value' => 'T-Square LMS', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'site_logo', 'value' => 'logo.png', 'type' => 'image', 'group_name' => 'general'],
            ['key' => 'facebook_url', 'value' => 'https://facebook.com/tsquare', 'type' => 'string', 'group_name' => 'social'],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'group_name' => 'general'],
            ['key' => 'contact_email', 'value' => 'info@tsquare.com', 'type' => 'string', 'group_name' => 'general'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
