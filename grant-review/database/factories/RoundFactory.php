<?php

namespace Database\Factories;

use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoundFactory extends Factory
{
    protected $model = Round::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'opens_at' => now()->subDays(30),
            'deadline_at' => now()->addDays(30),
            'status' => 'open',
        ];
    }
}
