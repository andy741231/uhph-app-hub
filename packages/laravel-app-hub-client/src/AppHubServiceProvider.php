<?php

namespace Uh\AppHub;

use Illuminate\Support\ServiceProvider;

class AppHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hub.php', 'hub');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/hub.php' => config_path('hub.php'),
        ], 'app-hub-config');
    }
}
