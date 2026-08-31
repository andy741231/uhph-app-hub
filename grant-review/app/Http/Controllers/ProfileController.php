<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteProfileRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Mail\ProfileCompleted;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function complete(Request $request): View
    {
        return view('profile.complete', [
            'user' => $request->user(),
        ]);
    }

    public function completeUpdate(CompleteProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        // Notify admins who want this notification
        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->get()
            ->filter(fn ($admin) => $admin->wantsEmail('notify_profile_completed'));

        $admins->each(fn ($admin) => Mail::to($admin)->send(new ProfileCompleted($user)));

        return Redirect::route('dashboard')->with('status', 'profile-completed');
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
