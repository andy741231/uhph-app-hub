<?php

namespace Database\Factories;

use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'abstract' => $this->faker->paragraph(),
            'amount_requested' => $this->faker->numberBetween(1000, 50000),
            'pdf_path' => 'submissions/'.$this->faker->uuid.'.pdf',
            'status' => 'submitted',
            'submitted_at' => now(),
        ];
    }
}
