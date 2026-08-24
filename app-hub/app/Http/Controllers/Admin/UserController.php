<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->withCount('applications')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_DISABLED])],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        User::create([
            ...$data,
            'is_admin' => $request->boolean('is_admin'),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'managedUser' => $user->load('applications'),
            'applications' => Application::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_DISABLED])],
            'is_admin' => ['nullable', 'boolean'],
        ]);
        $isAdmin = $request->boolean('is_admin');

        if ($request->user()->is($user) && ($data['status'] !== User::STATUS_ACTIVE || ! $isAdmin)) {
            throw ValidationException::withMessages([
                $data['status'] !== User::STATUS_ACTIVE ? 'status' : 'is_admin' => 'You cannot disable your own administrator access.',
            ]);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update([...$data, 'is_admin' => $isAdmin]);

        if (! $user->isActive()) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return redirect()->route('admin.users.edit', $user)->with('status', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('status', 'User deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'users' => ['required', 'array', 'min:1', 'max:1000'],
            'users.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $targetIds = collect($data['users'])->map(fn ($id) => (int) $id)->unique();
        $selfId = $request->user()->id;

        if ($targetIds->contains($selfId)) {
            throw ValidationException::withMessages([
                'users' => 'You cannot delete your own account.',
            ]);
        }

        DB::transaction(function () use ($targetIds): void {
            DB::table('sessions')->whereIn('user_id', $targetIds->all())->delete();
            User::query()->whereIn('id', $targetIds->all())->delete();
        });

        $count = $targetIds->count();

        return redirect()->route('admin.users.index')->with('status', "{$count} user(s) deleted successfully.");
    }
}
