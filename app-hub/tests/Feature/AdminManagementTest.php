<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_administrators_cannot_access_management_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/applications')->assertForbidden();
    }

    public function test_administrator_can_view_management_screens(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'roles' => ['admin', 'reviewer'],
        ]);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Users');
        $this->actingAs($admin)->get('/admin/users/create')->assertOk()->assertSee('Create user');
        $this->actingAs($admin)->get("/admin/users/{$admin->id}/edit")->assertOk()->assertSee('Application access');
        $this->actingAs($admin)->get('/admin/applications')->assertOk()->assertSee('Grant Review');
        $this->actingAs($admin)->get('/admin/applications/create')->assertOk()->assertSee('Register application');
        $this->actingAs($admin)->get("/admin/applications/{$application->key}/edit")->assertOk()->assertSee('Supported roles');
    }

    public function test_administrator_can_create_a_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Review User',
            'email' => ' REVIEWER@example.edu ',
            'password' => 'abcd1234',
            'password_confirmation' => 'abcd1234',
            'status' => User::STATUS_ACTIVE,
            'is_admin' => false,
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'reviewer@example.edu')->firstOrFail();
        $this->assertTrue(Hash::check('abcd1234', $user->password));
        $this->assertFalse($user->is_admin);
    }

    public function test_user_password_must_have_at_least_eight_characters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Review User',
            'email' => 'reviewer@example.edu',
            'password' => 'abc1234',
            'password_confirmation' => 'abc1234',
            'status' => User::STATUS_ACTIVE,
            'is_admin' => false,
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'reviewer@example.edu']);
    }

    public function test_administrator_can_register_an_application(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/applications', [
            'name' => 'Grant Review',
            'key' => 'grant-review',
            'path' => '/apps/grant-review',
            'frontchannel_logout_path' => '/apps/grant-review/auth/hub/logout',
            'roles' => 'admin, submitter, reviewer',
            'enabled' => true,
            'sort_order' => 10,
        ])->assertRedirect(route('admin.applications.index'));

        $application = Application::where('key', 'grant-review')->firstOrFail();
        $this->assertSame(['admin', 'submitter', 'reviewer'], $application->roles);
        $this->assertSame('/apps/grant-review/auth/hub/logout', $application->frontchannel_logout_path);
        $this->assertTrue($application->enabled);
    }

    public function test_application_roles_in_use_cannot_be_removed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'roles' => ['admin', 'reviewer'],
        ]);
        $user->applications()->attach($application, [
            'role' => 'reviewer',
            'granted_by' => $admin->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($admin)->from(route('admin.applications.edit', $application))
            ->put("/admin/applications/{$application->key}", [
                'name' => $application->name,
                'key' => $application->key,
                'path' => $application->path,
                'roles' => 'admin',
                'enabled' => true,
                'sort_order' => 10,
            ])->assertSessionHasErrors('roles');

        $this->assertSame(['admin', 'reviewer'], $application->fresh()->roles);
    }

    public function test_administrator_can_assign_and_revoke_application_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'roles' => ['admin', 'submitter', 'reviewer'],
        ]);

        $this->actingAs($admin)->put("/admin/users/{$user->id}/applications", [
            'applications' => [
                $application->id => [
                    'enabled' => true,
                    'role' => 'reviewer',
                ],
            ],
        ])->assertRedirect(route('admin.users.edit', $user));

        $this->assertDatabaseHas('application_user', [
            'application_id' => $application->id,
            'user_id' => $user->id,
            'role' => 'reviewer',
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/admin/users/{$user->id}/applications", [
            'applications' => [],
        ])->assertRedirect(route('admin.users.edit', $user));

        $this->assertDatabaseMissing('application_user', [
            'application_id' => $application->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_assignment_rejects_roles_not_supported_by_the_application(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'roles' => ['admin'],
        ]);

        $this->actingAs($admin)->from(route('admin.users.edit', $user))
            ->put("/admin/users/{$user->id}/applications", [
                'applications' => [
                    $application->id => [
                        'enabled' => true,
                        'role' => 'reviewer',
                    ],
                ],
            ])->assertSessionHasErrors("applications.{$application->id}.role");

        $this->assertDatabaseMissing('application_user', [
            'application_id' => $application->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_administrator_cannot_disable_their_own_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('admin.users.edit', $admin))
            ->put("/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'status' => User::STATUS_DISABLED,
                'is_admin' => true,
            ])->assertSessionHasErrors('status');

        $this->assertSame(User::STATUS_ACTIVE, $admin->fresh()->status);
    }
}
