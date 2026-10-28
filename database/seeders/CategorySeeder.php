<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => 'Web & eCommerce',
                'slug' => 'commerce',
                'type' => 'project',
                'sort_order' => 1,
            ],
            [
                'name' => 'Applications',
                'slug' => 'apps',
                'type' => 'project',
                'sort_order' => 2,
            ],
            [
                'name' => 'Cloud & servers',
                'slug' => 'cloud',
                'type' => 'project',
                'sort_order' => 3,
            ],
            [
                'name' => 'Development',
                'slug' => 'development',
                'type' => 'post',
                'sort_order' => 1,
            ],
            [
                'name' => 'WordPress',
                'slug' => 'wordpress',
                'type' => 'post',
                'sort_order' => 2,
            ],
            [
                'name' => 'Cloud & hosting',
                'slug' => 'cloud',
                'type' => 'post',
                'sort_order' => 3,
            ],
            [
                'name' => 'Linux & security',
                'slug' => 'security',
                'type' => 'post',
                'sort_order' => 4,
            ],
        ];

        foreach ($rows as $row) {
            Category::query()->firstOrCreate(['type' => $row['type'], 'slug' => $row['slug']], $row);
        }
    }
}
