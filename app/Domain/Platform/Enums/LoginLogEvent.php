<?php

namespace App\Domain\Platform\Enums;

enum LoginLogEvent: string
{
    case Login = 'login';
    case Failed = 'failed';
    case Logout = 'logout';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Inicio de sesión',
            self::Failed => 'Intento fallido',
            self::Logout => 'Cierre de sesión',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Login => 'success',
            self::Failed => 'danger',
            self::Logout => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case): array => [...$options, $case->value => $case->label()],
            [],
        );
    }
}
