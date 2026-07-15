<?php

namespace App\Listeners;

use App\Domain\Platform\Enums\LoginLogEvent;
use App\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Registra cada intento de acceso para auditoría (LOGIN-3): quién, cuándo,
 * desde qué IP. Escribe de forma síncrona, no encolada — un intento fallido
 * es justamente el tipo de evento que no puede perderse si el worker de colas
 * está caído.
 */
class LogAuthenticationEvent
{
    public function handleLogin(Login $event): void
    {
        LoginLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email,
            'event' => LoginLogEvent::Login->value,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'occurred_at' => now(),
        ]);

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()?->ip(),
        ])->saveQuietly();
    }

    public function handleFailed(Failed $event): void
    {
        LoginLog::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->user?->email ?? (string) ($event->credentials['email'] ?? ''),
            'event' => LoginLogEvent::Failed->value,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'occurred_at' => now(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        LoginLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email,
            'event' => LoginLogEvent::Logout->value,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
