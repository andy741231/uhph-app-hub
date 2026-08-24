<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Application::updateOrCreate(
            ['key' => 'grant-review'],
            [
                'name' => 'Grant Review',
                'path' => '/apps/grant-review',
                'callback_url' => '/apps/grant-review/auth/hub/callback',
                'roles' => ['admin', 'submitter', 'reviewer'],
                'enabled' => true,
                'sort_order' => 10,
            ],
        );

        Application::updateOrCreate(
            ['key' => 'flipbook'],
            [
                'name' => 'Flipbook',
                'path' => '/apps/flipbook',
                'callback_url' => '/apps/flipbook/auth/callback.php',
                'roles' => ['admin'],
                'enabled' => true,
                'sort_order' => 20,
            ],
        );
    }
}
