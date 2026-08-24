<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationCredentialController extends Controller
{
    public function store(Request $request, Application $application): RedirectResponse
    {
        if (! $application->callback_url) {
            throw ValidationException::withMessages([
                'callback_url' => 'Save an exact callback path before generating credentials.',
            ]);
        }

        do {
            $clientId = 'hub_'.Str::lower(Str::random(32));
        } while (Application::where('client_id', $clientId)->exists());

        $clientSecret = 'hubs_'.Str::random(64);
        DB::transaction(function () use ($application, $clientId, $clientSecret): void {
            $application->authorizationCodes()->delete();
            $application->update([
                'client_id' => $clientId,
                'client_secret_hash' => hash('sha256', $clientSecret),
            ]);
        });

        return redirect()->route('admin.applications.edit', $application)
            ->with('status', 'Client credentials rotated successfully.')
            ->with('client_secret', $clientSecret);
    }
}
