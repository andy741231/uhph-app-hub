<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function create(Request $request): View
    {
        $user = User::where('email', $request->email)->first();

        return view('auth.set-password', [
            'token' => $request->token,
            'email' => $request->email,
            'hideInvestigatorFields' => $user?->role === 'reviewer',
        ]);
    }

    public function store(SetPasswordRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'Invalid request.']);
        }

        $tokenHash = hash('sha256', $request->token);

        if (! hash_equals($user->invite_token_hash ?? '', $tokenHash)) {
            return back()->withErrors(['token' => 'Invalid or expired invitation link.']);
        }

        if ($user->invite_expires_at && $user->invite_expires_at->isPast()) {
            return back()->withErrors(['token' => 'This invitation link has expired. Contact the administrator for a new one.']);
        }

        $user->update([
            'password_hash' => Hash::make($request->password),
            'invite_token_hash' => null,
            'invite_expires_at' => null,
            'status' => 'active',
            'phone' => $request->phone,
            'department' => $request->department,
            'title' => $request->title,
            'peoplesoft_id' => $request->peoplesoft_id,
            'investigator_type' => $user->role === 'reviewer' ? $user->investigator_type : $request->investigator_type,
            'early_stage_investigator' => $user->role === 'reviewer' ? $user->early_stage_investigator : $request->boolean('early_stage_investigator'),
            'new_investigator' => $user->role === 'reviewer' ? $user->new_investigator : $request->boolean('new_investigator'),
        ]);

        auth()->login($user);

        return redirect()->route('dashboard');
    }
}
