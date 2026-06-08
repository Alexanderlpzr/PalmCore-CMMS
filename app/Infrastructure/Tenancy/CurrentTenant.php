<?php

namespace App\Infrastructure\Tenancy;

class CurrentTenant
{
    private static ?string $id = null;

    private static mixed $tenant = null;

    public static function set(mixed $tenant): void
    {
        static::$id = $tenant->id;
        static::$tenant = $tenant;
    }

    public static function id(): ?string
    {
        return static::$id;
    }

    public static function get(): mixed
    {
        return static::$tenant;
    }

    public static function clear(): void
    {
        static::$id = null;
        static::$tenant = null;
    }

    public static function isSet(): bool
    {
        return static::$id !== null;
    }
}
