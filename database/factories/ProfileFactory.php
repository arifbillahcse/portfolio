<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Profile> */
class ProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'main',
            'name' => 'Arif Billah',
            'headline' => 'From code to cloud.',
            'intro' => 'Web development and cloud infrastructure.',
            'biography' => 'A developer working across applications and infrastructure.',
            'journey' => [],
            'skill_groups' => [],
            'stats' => [],
        ];
    }
}
