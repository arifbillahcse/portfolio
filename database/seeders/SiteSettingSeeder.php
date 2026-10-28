<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'main',
                'brand_name' => 'Arif Portfolio',
                'tagline' => 'Web development & cloud infrastructure.',
                'contact_email' => 'emran.alarif8@gmail.com',
                'whatsapp_number' => '+8801779440297',
                'facebook_url' => 'https://facebook.com/arifbillahcse',
                'linkedin_url' => 'https://linkedin.com/in/arifbillahcse',
                'seo_title' => 'Arif Billah — Web Developer & Cloud Infrastructure',
                'seo_description' => 'Web development, WordPress, Laravel, Linux server administration, and cloud infrastructure with Arif Billah from Bangladesh.',
                'footer_text' => 'Arif. All rights reserved.',
            ],
        ];

        foreach ($rows as $row) {
            SiteSetting::query()->firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
