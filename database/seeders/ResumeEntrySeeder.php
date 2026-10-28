<?php

namespace Database\Seeders;

use App\Models\ResumeEntry;
use Illuminate\Database\Seeder;

class ResumeEntrySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'type' => 'education',
                'title' => 'BSc in Computer Science',
                'description' => 'I completed my BSc in Computer Science, where my interest in cloud computing grew alongside my background in web development.',
                'is_published' => true,
                'sort_order' => 1,
            ],
            [
                'type' => 'learning',
                'title' => 'Cloud computing & Linux',
                'description' => 'My studies inspired me to explore the systems behind websites, leading me toward Linux administration, hosting, and cloud infrastructure.',
                'is_published' => true,
                'sort_order' => 2,
            ],
            [
                'type' => 'experience',
                'title' => 'Freelance web developer & system administrator',
                'organization' => 'Fiverr',
                'start_label' => '2020',
                'is_current' => true,
                'description' => 'Web development and server services for clients.',
                'is_published' => true,
                'sort_order' => 3,
            ],
            [
                'type' => 'experience',
                'title' => 'Freelance projects',
                'organization' => 'Upwork',
                'description' => 'Development and system administration services.',
                'is_published' => true,
                'sort_order' => 4,
            ],
            [
                'type' => 'experience',
                'title' => 'Founder',
                'organization' => 'Hostorio.com',
                'description' => 'Web hosting, server administration, and customer support.',
                'is_current' => true,
                'is_published' => true,
                'sort_order' => 5,
            ],
            [
                'type' => 'experience',
                'title' => 'Founder',
                'organization' => 'Softorio.com',
                'description' => 'Web development and digital solutions.',
                'is_current' => true,
                'is_published' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($rows as $row) {
            ResumeEntry::query()->firstOrCreate(['type' => $row['type'], 'title' => $row['title'], 'organization' => $row['organization'] ?? null], $row);
        }
    }
}
