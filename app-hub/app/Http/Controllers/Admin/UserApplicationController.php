<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserApplicationController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'applications' => ['present', 'array'],
            'applications.*.enabled' => ['nullable', 'boolean'],
            'applications.*.role' => ['nullable', 'string', 'max:50'],
        ]);
        $selected = collect($request->input('applications', []))
            ->filter(fn (array $assignment): bool => filter_var($assignment['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $applications = Application::query()->whereIn('id', $selected->keys())->get()->keyBy('id');
        $sync = [];

        foreach ($selected as $applicationId => $assignment) {
            $application = $applications->get((int) $applicationId);

            if (! $application) {
                throw ValidationException::withMessages([
                    "applications.{$applicationId}.enabled" => 'The selected application does not exist.',
                ]);
            }

            $role = Str::lower(trim((string) ($assignment['role'] ?? '')));
            $roles = $application->roles ?? [];

            if ($roles !== [] && ! in_array($role, $roles, true)) {
                throw ValidationException::withMessages([
                    "applications.{$applicationId}.role" => 'Select a role supported by this application.',
                ]);
            }

            $sync[$application->id] = [
                'role' => $roles === [] ? null : $role,
                'granted_by' => $request->user()->id,
                'granted_at' => now(),
            ];
        }

        $user->applications()->sync($sync);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Application access updated successfully.');
    }
}
