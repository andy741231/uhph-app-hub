<?php

namespace Tests\Unit;

use App\Models\Application;
use Tests\TestCase;

class ApplicationLaunchPathTest extends TestCase
{
    public function test_a_configured_flipbook_launches_at_its_login_page(): void
    {
        $application = $this->flipbook(['path' => '/apps/flipbook']);

        $this->assertSame('/apps/flipbook/auth/login.php', $application->launchPath());
    }

    public function test_the_flipbook_login_path_normalizes_trailing_slashes(): void
    {
        $application = $this->flipbook(['path' => '/apps/flipbook/']);

        $this->assertSame('/apps/flipbook/auth/login.php', $application->launchPath());
    }

    public function test_a_flipbook_without_sso_credentials_keeps_its_public_root(): void
    {
        $application = new Application(['key' => 'flipbook', 'path' => '/apps/flipbook']);

        $this->assertSame('/apps/flipbook', $application->launchPath());
    }

    public function test_other_configured_applications_keep_their_registered_path(): void
    {
        $application = new Application([
            'key' => 'grant-review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'secret'),
        ]);

        $this->assertSame('/apps/grant-review', $application->launchPath());
    }

    private function flipbook(array $attributes = []): Application
    {
        return new Application($attributes + [
            'key' => 'flipbook',
            'path' => '/apps/flipbook',
            'callback_url' => '/apps/flipbook/auth/callback.php',
            'client_id' => 'hub_flipbook',
            'client_secret_hash' => hash('sha256', 'secret'),
        ]);
    }
}
