<?php

namespace App\Support;

use InvalidArgumentException;

enum LoginMode: string
{
    case Sso = 'sso';
    case Local = 'local';
    case Hybrid = 'hybrid';

    public static function current(): self
    {
        $value = strtolower(trim((string) config('hub.login_mode', self::Local->value)));

        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Unsupported HUB_LOGIN_MODE [{$value}].");
    }

    public function allows(string $method): bool
    {
        return match ($method) {
            'sso' => $this !== self::Local,
            'local' => $this !== self::Sso,
            default => false,
        };
    }

    public function allowsSso(): bool
    {
        return $this->allows('sso');
    }

    public function allowsLocal(): bool
    {
        return $this->allows('local');
    }
}
