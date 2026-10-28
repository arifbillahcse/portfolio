<?php

namespace Database\Factories;

use App\Models\SeoRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeoRedirect> */
class SeoRedirectFactory extends Factory
{
    public function definition(): array
    {
        return ['source_path' => '/legacy-'.fake()->unique()->slug(), 'destination_path' => '/about', 'status_code' => 301, 'enabled' => false];
    }
}
