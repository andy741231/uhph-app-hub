<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('admin.settings.edit', [
            'emailPreferences' => $user->email_preferences ?? User::defaultEmailPreferences(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'email_preferences' => ['sometimes', 'array'],
            'email_preferences.*' => ['boolean'],
        ]);

        // Update per-user email preferences
        if (array_key_exists('email_preferences', $validated)) {
            $user->email_preferences = array_merge(
                $user->email_preferences ?? User::defaultEmailPreferences(),
                $validated['email_preferences']
            );
            $user->save();
        }

        return redirect()->route('settings.edit')->with('status', 'Settings updated successfully.');
    }
}
