<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;

class RootRedirectController extends Controller
{
    /**
     * Redirect the root URL to the login page.
     *
     * Uses route() helper so the generated URL includes the app's base path
     * (e.g. /apps/grant-review/login), not just /login at the domain root.
     * This is a controller (not a closure) so it is compatible with
     * `php artisan route:cache`.
     */
    public function __invoke()
    {
        // TEMP DIAGNOSTIC
        $loginUrl = route('login');
        file_put_contents(
            storage_path('logs/root-redirect-debug.log'),
            date('Y-m-d H:i:s') . " loginUrl=" . $loginUrl . " isGuest=" . (auth()->guest() ? 'yes' : 'no') . "\n",
            FILE_APPEND
        );

        return redirect()->route('login');
    }
}
