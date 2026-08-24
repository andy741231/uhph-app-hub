<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmergencyLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmergencySessionController extends Controller
{
    public function create(): View
    {
        return view('auth.emergency-login');
    }

    public function store(EmergencyLoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->put('emergency_authenticated', true);

        return redirect()->route('admin.dashboard');
    }
}
