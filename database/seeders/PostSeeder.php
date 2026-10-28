<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'title' => 'From code to cloud: the complete website journey',
                'slug' => 'from-code-to-cloud-the-complete-website-journey',
                'category_slug' => 'cloud',
                'excerpt' => 'A look at how development, deployment, and infrastructure fit into one connected project.',
                'body' => 'This is a sample article draft. Add your original article content before publishing.',
                'is_demo' => true,
                'is_published' => false,
                'is_featured' => true,
                'reading_minutes' => 5,
                'sort_order' => 1,
            ],
            [
                'title' => 'Planning a Laravel application around your business',
                'slug' => 'planning-a-laravel-application-around-your-business',
                'category_slug' => 'development',
                'excerpt' => 'Turning a list of requirements into a useful application with clear workflows.',
                'body' => 'This is a sample article draft. Add your original article content before publishing.',
                'is_demo' => true,
                'is_published' => false,
                'is_featured' => false,
                'reading_minutes' => 5,
                'sort_order' => 2,
            ],
            [
                'title' => 'What to consider before building a WooCommerce store',
                'slug' => 'what-to-consider-before-building-a-woocommerce-store',
                'category_slug' => 'wordpress',
                'excerpt' => 'A sample planning checklist for products, checkout, content, and integrations.',
                'body' => 'This is a sample article draft. Add your original article content before publishing.',
                'is_demo' => true,
                'is_published' => false,
                'is_featured' => false,
                'reading_minutes' => 5,
                'sort_order' => 3,
            ],
            [
                'title' => 'A practical approach to Linux server administration',
                'slug' => 'a-practical-approach-to-linux-server-administration',
                'category_slug' => 'security',
                'excerpt' => 'An introduction to the areas involved in managing a website’s server environment.',
                'body' => 'This is a sample article draft. Add your original article content before publishing.',
                'is_demo' => true,
                'is_published' => false,
                'is_featured' => false,
                'reading_minutes' => 5,
                'sort_order' => 4,
            ],
            [
                'title' => 'Choosing a hosting environment for your next project',
                'slug' => 'choosing-a-hosting-environment-for-your-next-project',
                'category_slug' => 'cloud',
                'excerpt' => 'Framing the right questions about your application, infrastructure, and support needs.',
                'body' => 'This is a sample article draft. Add your original article content before publishing.',
                'is_demo' => true,
                'is_published' => false,
                'is_featured' => false,
                'reading_minutes' => 5,
                'sort_order' => 5,
            ],
            [
                'title' => 'Connecting applications with REST APIs',
                'slug' => 'connecting-applications-with-rest-apis',
                'category_slug' => 'development',
                'excerpt' => 'A sample introduction to the role of APIs in connected business workflows.',
                'body' => 'This is a sample article draft. Add your original article content before publishing.',
                'is_demo' => true,
                'is_published' => false,
                'is_featured' => false,
                'reading_minutes' => 5,
                'sort_order' => 6,
            ],
            [
                'title' => 'Website performance: where to start your review',
                'slug' => 'website-performance-where-to-start-your-review',
                'category_slug' => 'wordpress',
                'excerpt' => 'An overview of the questions to ask when a website feels slow or difficult to use.',
                'body' => 'This is a sample article draft. Add your original article content before publishing.',
                'is_demo' => true,
                'is_published' => false,
                'is_featured' => false,
                'reading_minutes' => 5,
                'sort_order' => 7,
            ],
            [
                'title' => 'Building a website and server maintenance routine',
                'slug' => 'building-a-website-and-server-maintenance-routine',
                'category_slug' => 'security',
                'excerpt' => 'A sample outline for thinking about updates, backups, monitoring, and ongoing care.',
                'body' => 'This is a sample article draft. Add your original article content before publishing.',
                'is_demo' => true,
                'is_published' => false,
                'is_featured' => false,
                'reading_minutes' => 5,
                'sort_order' => 8,
            ],
        ];

        foreach ($rows as $row) {
            $row['category_id'] = Category::query()->where('type', 'post')->where('slug', $row['category_slug'])->value('id');
            unset($row['category_slug']);
            Post::query()->firstOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
