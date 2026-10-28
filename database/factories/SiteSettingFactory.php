<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteSetting> */
class SiteSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'main',
            'brand_name' => 'Arif Portfolio',
            'contact_email' => 'owner@example.com',
            'whatsapp_number' => '+8801779440297',
        ];
    }
}
