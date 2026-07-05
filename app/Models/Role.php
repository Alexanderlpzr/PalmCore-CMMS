<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /** Turn a kebab/snake-case role slug into a human label: "administrador-general" → "Administrador General". */
    public static function humanizeName(string $name): string
    {
        return Str::headline(str_replace(['-', '_'], ' ', $name));
    }
}
