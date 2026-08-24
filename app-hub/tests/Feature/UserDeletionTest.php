<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuthorizationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_administrators_cannot_delete_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($user)->delete("/admin/users/{$target->id}")->assertForbidden();
        $this->actingAs($user)->delete('/admin/users/bulk', ['users' => [$target->id]])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_guests_are_redirected_to_login_for_delete_routes(): void
    {
        $target = User::factory()->create();

        $this->delete("/admin/users/{$target->id}")->assertRedirect(route('login'));
        $this->delete('/admin/users/bulk', ['users' => [$target->id]])->assertRedirect(route('login'));
    }

    public function test_administrator_can_delete_a_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['name' => 'Legacy Reviewer']);

        $this->actingAs($admin)->from(route('admin.users.edit', $target))
            ->delete("/admin/users/{$target->id}")
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_administrator_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('admin.users.edit', $admin))
            ->delete("/admin/users/{$admin->id}")
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_delete_cascades_application_assignments_and_authorization_codes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'roles' => ['admin', 'reviewer'],
        ]);
        $target->applications()->attach($application, [
            'role' => 'reviewer',
            'granted_by' => $admin->id,
            'granted_at' => now(),
        ]);
        AuthorizationCode::create([
            'token_hash' => hash('sha256', 'test-token-1'),
            'application_id' => $application->id,
            'user_id' => $target->id,
            'redirect_uri' => '/apps/grant-review/auth/hub/callback',
            'role' => 'reviewer',
            'expires_at' => now()->addMinute(),
        ]);

        $this->actingAs($admin)->delete("/admin/users/{$target->id}");

        $this->assertDatabaseMissing('application_user', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('authorization_codes', ['user_id' => $target->id]);
    }

    public function test_delete_preserves_audit_history_with_null_user_reference(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        DB::table('login_audits')->insert([
            'user_id' => $target->id,
            'email' => $target->email,
            'succeeded' => true,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test/1.0',
            'created_at' => now(),
        ]);
        DB::table('application_launch_audits')->insert([
            'user_id' => $target->id,
            'application_id' => null,
            'succeeded' => true,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test/1.0',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->delete("/admin/users/{$target->id}");

        $this->assertDatabaseHas('login_audits', ['email' => $target->email, 'user_id' => null]);
        $this->assertDatabaseHas('application_launch_audits', ['user_id' => null]);
    }

    public function test_delete_clears_active_sessions_for_the_deleted_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'test-session-1',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test/1.0',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)->delete("/admin/users/{$target->id}");

        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    }

    public function test_administrator_can_bulk_delete_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $a = User::factory()->create(['name' => 'A']);
        $b = User::factory()->create(['name' => 'B']);
        $c = User::factory()->create(['name' => 'C']);

        $this->actingAs($admin)->from(route('admin.users.index'))
            ->delete('/admin/users/bulk', ['users' => [$a->id, $b->id, $c->id]])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['id' => $a->id]);
        $this->assertDatabaseMissing('users', ['id' => $b->id]);
        $this->assertDatabaseMissing('users', ['id' => $c->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_bulk_delete_rejects_self_in_selection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin)->from(route('admin.users.index'))
            ->delete('/admin/users/bulk', ['users' => [$target->id, $admin->id]])
            ->assertSessionHasErrors('users');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_bulk_delete_validates_required_selection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin)->from(route('admin.users.index'))
            ->delete('/admin/users/bulk', ['users' => []])
            ->assertSessionHasErrors('users');

        $this->actingAs($admin)->from(route('admin.users.index'))
            ->delete('/admin/users/bulk')
            ->assertSessionHasErrors('users');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_bulk_delete_rejects_unknown_user_ids(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin)->from(route('admin.users.index'))
            ->delete('/admin/users/bulk', ['users' => [$target->id, 999999]])
            ->assertSessionHasErrors('users.1');

        // Transaction should roll back, so the valid target remains.
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_bulk_delete_deduplicates_ids_and_counts_unique_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->from(route('admin.users.index'))
            ->delete('/admin/users/bulk', ['users' => [$target->id, $target->id]])
            ->assertRedirect(route('admin.users.index'));

        $status = session('status');
        $this->assertStringContainsString('1 user(s) deleted', (string) $status);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_edit_page_shows_delete_form_for_other_users_but_not_self(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.users.edit', $admin))
            ->assertOk()
            ->assertSee('You cannot delete your own account')
            ->assertDontSee('Delete '.$admin->name);

        $target = User::factory()->create(['name' => 'Target Person']);
        $this->actingAs($admin)->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('Delete Target Person');
    }

    public function test_index_page_renders_bulk_delete_controls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['name' => 'Bulk Target']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Delete selected')
            ->assertSee('select-all-users')
            ->assertSee('bulk-users-form');
    }

    public function test_index_page_omits_checkbox_for_self(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Self Admin']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $response->assertOk();
        $body = $response->getContent();
        $needle = '<input type="checkbox" name="users[]" value="'.$admin->id.'"';
        $this->assertStringNotContainsString($needle, $body);
    }
}
