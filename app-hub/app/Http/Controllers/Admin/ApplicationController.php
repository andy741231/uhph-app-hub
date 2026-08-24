<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(): View
    {
        return view('admin.applications.index', [
            'applications' => Application::query()->withCount('users')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.applications.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Application::create($this->validated($request));

        return redirect()->route('admin.applications.index')->with('status', 'Application registered successfully.');
    }

    public function edit(Application $application): View
    {
        return view('admin.applications.edit', compact('application'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $data = $this->validated($request, $application);
        $assignedRoles = $application->users()->wherePivotNotNull('role')->pluck('application_user.role')->unique();

        if ($assignedRoles->diff($data['roles'])->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'A role cannot be removed while it is assigned to a user.',
            ]);
        }

        $application->update($data);

        return redirect()->route('admin.applications.edit', $application)->with('status', 'Application updated successfully.');
    }

    private function validated(Request $request, ?Application $application = null): array
    {
        $callback = trim((string) $request->input('callback_url'));
        $request->merge([
            'key' => Str::lower(trim((string) $request->input('key'))),
            'path' => rtrim(trim((string) $request->input('path')), '/'),
            'callback_url' => $callback === '' ? null : rtrim($callback, '/'),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('applications')->ignore($application)],
            'path' => ['required', 'string', 'max:2048', 'regex:#^/apps/[A-Za-z0-9_~-]+(?:/[A-Za-z0-9._~-]+)*$#', 'not_regex:#(?:^|/)\.{1,2}(?:/|$)#'],
            'callback_url' => ['nullable', 'string', 'max:2048', 'regex:#^/apps/[A-Za-z0-9_~-]+(?:/[A-Za-z0-9._~-]+)*$#', 'not_regex:#(?:^|/)\.{1,2}(?:/|$)#'],
            'roles' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
        $roles = collect(explode(',', (string) ($data['roles'] ?? '')))
            ->map(fn (string $role): string => Str::lower(trim($role)))
            ->filter()
            ->unique()
            ->values();

        if ($roles->contains(fn (string $role): bool => preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $role) !== 1)) {
            throw ValidationException::withMessages([
                'roles' => 'Roles must begin with a letter and contain only letters, numbers, underscores, or hyphens.',
            ]);
        }

        return [
            ...$data,
            'roles' => $roles->all(),
            'enabled' => $request->boolean('enabled'),
        ];
    }
}
