<?php

namespace App\Http\Controllers\Sso;

use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GlobalLogout
{
    private const CACHE_PREFIX = 'sso-global-logout:';

    public function start(Request $request, ?Application $context = null): RedirectResponse
    {
        $steps = Application::query()
            ->where('enabled', true)
            ->whereNotNull('frontchannel_logout_path')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Application $application): bool => $application->hasSafeFrontchannelLogoutPath())
            ->map(fn (Application $application): array => [
                'application' => $application->key,
                'path' => $application->frontchannel_logout_path,
            ])
            ->values()
            ->all();
        $returnUrl = route('login', $context ? ['application' => $context->key] : []);

        if ($steps === []) {
            return redirect()->to($returnUrl);
        }

        $token = Str::random(64);
        Cache::put($this->cacheKey($token), [
            'steps' => $steps,
            'return_url' => $returnUrl,
            'origin' => $request->getSchemeAndHttpHost(),
        ], now()->addMinutes(2));

        return redirect()->away($this->stepUrl($request->getSchemeAndHttpHost(), $steps[0]['path'], $token))
            ->withHeaders(['Referrer-Policy' => 'no-referrer']);
    }

    public function continue(Request $request): JsonResponse
    {
        $input = $request->validate([
            'application' => ['required', 'string', 'max:100'],
            'logout_token' => ['required', 'string', 'size:64'],
        ]);
        $key = $this->cacheKey($input['logout_token']);
        $state = Cache::get($key);
        $step = is_array($state) ? ($state['steps'][0] ?? null) : null;

        if (! is_array($step) || ! hash_equals((string) $step['application'], $input['application'])) {
            return response()->json(['error' => 'invalid_logout_token'], 400)
                ->header('Cache-Control', 'no-store, private');
        }

        array_shift($state['steps']);
        if ($state['steps'] === []) {
            Cache::forget($key);
            $nextUrl = $state['return_url'];
        } else {
            Cache::put($key, $state, now()->addMinutes(2));
            $nextUrl = $this->stepUrl($state['origin'], $state['steps'][0]['path'], $input['logout_token']);
        }

        return response()->json(['next_url' => $nextUrl])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.hash('sha256', $token);
    }

    private function stepUrl(string $origin, string $path, string $token): string
    {
        return $origin.$path.'?'.http_build_query(['logout_token' => $token], '', '&', PHP_QUERY_RFC3986);
    }
}
