<?php

namespace App\Http\Controllers\Sso;

use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationClientAuthenticator
{
    public function authenticate(Request $request): ?Application
    {
        [$clientId, $clientSecret] = $this->basicCredentials($request);
        $application = $clientId ? Application::where('client_id', $clientId)->first() : null;
        $providedHash = hash('sha256', (string) $clientSecret);
        $expectedHash = $application?->client_secret_hash ?? str_repeat('0', 64);
        $registeredSecretValid = hash_equals($expectedHash, $providedHash);
        $localSecret = (string) config('hub.local_client.secret');
        $localSecretValid = app()->environment('local')
            && filled($localSecret)
            && in_array((string) $application?->key, config('hub.local_client.application_keys', []), true)
            && hash_equals(hash('sha256', $localSecret), $providedHash);

        return $clientSecret && ($registeredSecretValid || $localSecretValid) && $application?->enabled
            ? $application
            : null;
    }

    private function basicCredentials(Request $request): array
    {
        $authorization = $request->header('Authorization', '');

        if (! str_starts_with($authorization, 'Basic ')) {
            return [null, null];
        }

        $decoded = base64_decode(substr($authorization, 6), true);

        return $decoded !== false && str_contains($decoded, ':')
            ? explode(':', $decoded, 2)
            : [null, null];
    }
}
