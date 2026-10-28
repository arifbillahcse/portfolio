<?php

namespace Database\Factories;

use App\Models\ResumeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResumeEntry> */
class ResumeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'education',
            'title' => fake()->sentence(3),
            'organization' => fake()->company(),
            'description' => fake()->paragraph(),
            'is_current' => false,
            'is_published' => false,
            'sort_order' => 0,
        ];
    }
}
