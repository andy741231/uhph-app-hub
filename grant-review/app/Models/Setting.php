<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'boolean',
    ];

    /**
     * Get a boolean setting by key, returning a default if not found.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Set a boolean setting by key (upsert).
     */
    public static function setBool(string $key, bool $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
