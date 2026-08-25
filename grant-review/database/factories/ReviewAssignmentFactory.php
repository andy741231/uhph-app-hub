<?php

namespace Database\Factories;

use App\Models\ReviewAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewAssignmentFactory extends Factory
{
    protected $model = ReviewAssignment::class;

    public function definition(): array
    {
        return [
            'assigned_at' => now(),
        ];
    }
}
