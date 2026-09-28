<?php

namespace Tests\Unit;

use App\Support\LoginMode;
use InvalidArgumentException;
use Tests\TestCase;

class LoginModeTest extends TestCase
{
    public function test_allows_matches_each_mode(): void
    {
        $this->assertTrue(LoginMode::Sso->allows('sso'));
        $this->assertFalse(LoginMode::Sso->allows('local'));
        $this->assertTrue(LoginMode::Local->allows('local'));
        $this->assertFalse(LoginMode::Local->allows('sso'));
        $this->assertTrue(LoginMode::Hybrid->allows('sso'));
        $this->assertTrue(LoginMode::Hybrid->allows('local'));
        $this->assertFalse(LoginMode::Hybrid->allows('other'));
    }

    public function test_current_reads_the_configured_mode_case_insensitively(): void
    {
        config()->set('hub.login_mode', ' HYBRID ');

        $this->assertSame(LoginMode::Hybrid, LoginMode::current());
    }

    public function test_current_rejects_unsupported_values(): void
    {
        config()->set('hub.login_mode', 'oauth');

        $this->expectException(InvalidArgumentException::class);

        LoginMode::current();
    }
}
