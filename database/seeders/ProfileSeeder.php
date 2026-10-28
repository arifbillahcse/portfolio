<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'main',
                'name' => 'Arif Billah',
                'headline' => 'A developer’s mindset. A passion for cloud.',
                'intro' => 'I build websites and take care of the systems behind them—bringing development and infrastructure together for your business.',
                'biography' => 'I’m a web developer and system administrator from Bangladesh. I started my career building websites. While completing my BSc in Computer Science, I became passionate about cloud computing and infrastructure. Today, I work across application development, WordPress, eCommerce, Linux servers, and cloud infrastructure.',
                'home_heading' => 'Hi, I’m Arif. I build sites and keep servers running.',
                'home_intro' => 'Full-stack developer and Linux system administrator from Bangladesh. I handle the application and the server, so you deal with one person.',
                'journey' => [
                    [
                        'label' => 'The beginning',
                        'title' => 'Web development',
                        'description' => 'I began my career building websites and solving practical business problems with code.',
                    ],
                    [
                        'label' => 'The foundation',
                        'title' => 'Computer Science',
                        'description' => 'During my BSc, I developed a deeper understanding of systems, networks, and software engineering.',
                    ],
                    [
                        'label' => 'Today',
                        'title' => 'Development + cloud',
                        'description' => 'Now I build applications and manage the secure infrastructure that keeps them running.',
                    ],
                ],
                'skill_groups' => [
                    [
                        'title' => 'Web development',
                        'skills' => [
                            'WordPress',
                            'Laravel',
                            'WooCommerce',
                            'PHP',
                            'Elementor',
                            'ACF',
                            'REST APIs',
                            'AI integration',
                        ],
                    ],
                    [
                        'title' => 'Cloud & infrastructure',
                        'skills' => [
                            'Linux',
                            'AWS',
                            'Azure',
                            'Google Cloud',
                            'Apache',
                            'Nginx',
                            'Web Hosting',
                            'Server Security',
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'value' => '200+',
                        'label' => 'Websites built',
                    ],
                    [
                        'value' => '100+',
                        'label' => 'Server configurations',
                    ],
                    [
                        'value' => 'Since 2020',
                        'label' => 'Working on Fiverr',
                    ],
                ],
                'businesses' => [
                    [
                        'name' => 'Hostorio.com',
                        'focus' => 'Web hosting',
                        'description' => 'A hosting business where I put infrastructure management and customer support into practice.',
                    ],
                    [
                        'name' => 'Softorio.com',
                        'focus' => 'Web development agency',
                        'description' => 'A web development agency focused on creating websites and digital solutions for clients.',
                    ],
                ],
                'support_story' => 'I have worked on Fiverr since 2020 and also work on Upwork. Supporting customers through my hosting company has taught me to troubleshoot efficiently and communicate clearly under pressure. The experience shared in my story includes resolving over 500 problems in 2023.',
                'vision_quote' => 'I’m passionate about cloud computing and creating solutions that make a meaningful difference.',
                'vision_note' => 'My journey has included setbacks and successes. Persistence, continuous learning, and sharing knowledge have helped me move forward.',
            ],
        ];

        foreach ($rows as $row) {
            Profile::query()->firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
