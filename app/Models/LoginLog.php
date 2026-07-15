<?php

namespace App\Models;

use App\Domain\Platform\Enums\LoginLogEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un intento de acceso — exitoso, fallido o cierre de sesión. Platform-level
 * (no BelongsToTenant): el login ocurre antes de que exista un contexto de
 * empresa, y un intento fallido puede no enlazar a ningún usuario real.
 */
#[Fillable([
    'user_id',
    'email',
    'event',
    'ip_address',
    'user_agent',
    'occurred_at',
])]
class LoginLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'event' => LoginLogEvent::class,
            'occurred_at' => 'datetime',
        ];
    }
}
