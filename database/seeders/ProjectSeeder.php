<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'title' => 'Commerce storefront',
                'slug' => 'commerce-storefront',
                'category_slug' => 'commerce',
                'excerpt' => 'An online shopping experience with product discovery, a focused cart, and a straightforward checkout.',
                'body' => 'This is an illustrative project concept. Replace this text with your real project case study.',
                'tools' => [
                    'WordPress',
                    'WooCommerce',
                    'PHP',
                ],
                'gallery' => [],
                'is_demo' => true,
                'is_published' => false,
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'title' => 'Business operations dashboard',
                'slug' => 'business-operations-dashboard',
                'category_slug' => 'apps',
                'excerpt' => 'A central workspace for business records, daily tasks, and useful reporting.',
                'body' => 'This is an illustrative project concept. Replace this text with your real project case study.',
                'tools' => [
                    'Laravel',
                    'PHP',
                    'REST API',
                ],
                'gallery' => [],
                'is_demo' => true,
                'is_published' => false,
                'sort_order' => 2,
                'is_featured' => false,
            ],
            [
                'title' => 'Cloud hosting environment',
                'slug' => 'cloud-hosting-environment',
                'category_slug' => 'cloud',
                'excerpt' => 'A deployment concept connecting an application to the infrastructure and tools needed to run it.',
                'body' => 'This is an illustrative project concept. Replace this text with your real project case study.',
                'tools' => [
                    'Linux',
                    'Nginx',
                    'Cloud',
                ],
                'gallery' => [],
                'is_demo' => true,
                'is_published' => false,
                'sort_order' => 3,
                'is_featured' => false,
            ],
            [
                'title' => 'Content-led business website',
                'slug' => 'content-led-business-website',
                'category_slug' => 'commerce',
                'excerpt' => 'An editable website that introduces a business, explains its services, and helps customers get in touch.',
                'body' => 'This is an illustrative project concept. Replace this text with your real project case study.',
                'tools' => [
                    'WordPress',
                    'Elementor',
                    'ACF',
                ],
                'gallery' => [],
                'is_demo' => true,
                'is_published' => false,
                'sort_order' => 4,
                'is_featured' => false,
            ],
            [
                'title' => 'Customer support assistant',
                'slug' => 'customer-support-assistant',
                'category_slug' => 'apps',
                'excerpt' => 'An interface concept for helping visitors find answers and reach the right support channel.',
                'body' => 'This is an illustrative project concept. Replace this text with your real project case study.',
                'tools' => [
                    'API integration',
                    'AI chatbot',
                    'PHP',
                ],
                'gallery' => [],
                'is_demo' => true,
                'is_published' => false,
                'sort_order' => 5,
                'is_featured' => false,
            ],
            [
                'title' => 'Website migration & server care',
                'slug' => 'website-migration-and-server-care',
                'category_slug' => 'cloud',
                'excerpt' => 'A migration workflow concept covering the move, domain configuration, and ongoing infrastructure care.',
                'body' => 'This is an illustrative project concept. Replace this text with your real project case study.',
                'tools' => [
                    'DNS',
                    'Linux',
                    'Cloudflare',
                ],
                'gallery' => [],
                'is_demo' => true,
                'is_published' => false,
                'sort_order' => 6,
                'is_featured' => false,
            ],
        ];

        foreach ($rows as $row) {
            $row['category_id'] = Category::query()->where('type', 'project')->where('slug', $row['category_slug'])->value('id');
            unset($row['category_slug']);
            Project::query()->firstOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
