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
                'name' => 'Pilot Central',
                'path' => '/apps/grant-review',
                'callback_url' => '/apps/grant-review/auth/hub/callback',
                'frontchannel_logout_path' => '/apps/grant-review/auth/hub/logout',
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
                'frontchannel_logout_path' => '/apps/flipbook/auth/hub-logout.php',
                'roles' => ['admin', 'user'],
                'enabled' => true,
                'sort_order' => 20,
            ],
        );

        // Registered disabled until production credentials are assigned
        // through the Hub admin UI and the deployment is verified. Uses
        // firstOrCreate so re-seeding never re-disables the app or clobbers
        // issued credentials/assignments after launch.
        Application::firstOrCreate(
            ['key' => 'doc-review'],
            [
                'name' => 'Document Reviewer',
                'path' => '/apps/doc-review',
                'callback_url' => '/apps/doc-review/auth/hub/callback',
                'frontchannel_logout_path' => '/apps/doc-review/auth/hub/logout',
                'roles' => ['admin', 'user'],
                'enabled' => false,
                'sort_order' => 30,
            ],
        );
    }
}
