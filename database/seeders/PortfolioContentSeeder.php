<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PortfolioContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class, ProjectSeeder::class, PostSeeder::class, ServiceSeeder::class,
            ProfileSeeder::class, ResumeEntrySeeder::class, SiteSettingSeeder::class,
        ]);
    }
}
