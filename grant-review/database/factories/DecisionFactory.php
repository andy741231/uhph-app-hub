<?php

namespace Database\Factories;

use App\Models\Decision;
use Illuminate\Database\Eloquent\Factories\Factory;

class DecisionFactory extends Factory
{
    protected $model = Decision::class;

    public function definition(): array
    {
        return [
            'outcome' => $this->faker->randomElement(['funded', 'not_funded']),
            'amount_awarded' => $this->faker->numberBetween(0, 50000),
            'decided_at' => now(),
        ];
    }
}
