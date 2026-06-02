<?php

namespace Database\Seeders;

use App\Models\Setting;
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
            ['key' => 'contact_email', 'value' => 'info@tsquare.com', 'type' => 'string', 'group_name' => 'general'],

            // social media urls
            ['key' => 'facebook_url', 'value' => 'https://facebook.com/tsquare', 'type' => 'string', 'group_name' => 'social'],
            ['key' => 'instagram_url', 'value' => 'https://instagram.com/tsquare', 'type' => 'string', 'group_name' => 'social'],
            ['key' => 'linkedin_url', 'value' => 'https://linkedin.com/tsquare', 'type' => 'string', 'group_name' => 'social'],

            // whatsapp number
            ['key' => 'whatsapp', 'value' => '01210608027', 'type' => 'string', 'group_name' => 'social'],

            // maintenance mode
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group_name' => 'general'],

            // sections media
            ['key' => 'discovery_media', 'value' => '[]', 'type' => 'json', 'group_name' => 'general'],
            ['key' => 'about_media', 'value' => '[]', 'type' => 'json', 'group_name' => 'general'],
            ['key' => 'hero_media', 'value' => '[]', 'type' => 'json', 'group_name' => 'general'],
            ['key' => 'hero_title_en', 'value' => 'Start Your Tech Journey with  ', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'hero_title_ar', 'value' => 'ابدأ رحلتك التقنية بـ ', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'hero_title_highlight_en', 'value' => 'Confidence', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'hero_title_highlight_ar', 'value' => 'ثقة', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'hero_subtitle_en', 'value' => 'Master coding from zero to hero with industry experts. Get hands-on projects, real mentorship, and land your dream job.', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'hero_subtitle_ar', 'value' => 'أتقن البرمجة من الصفر إلى الاحتراف مع خبراء الصناعة. احصل على مشاريع عملية، إرشادات حقيقية، وتحقق حلمك الوظيفي.', 'type' => 'string', 'group_name' => 'general'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
