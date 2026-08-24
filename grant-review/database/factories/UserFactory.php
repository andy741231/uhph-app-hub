<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'department' => fake()->optional()->company(),
            'role' => 'submitter',
            'status' => 'active',
        ];
    }
}
