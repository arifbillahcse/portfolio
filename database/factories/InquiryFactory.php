<?php

namespace Database\Factories;

use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inquiry> */
class InquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+8801779440297',
            'message' => fake()->paragraph(),
            'source' => 'contact',
            'status' => 'new',
            'notification_status' => 'pending',
        ];
    }
}
