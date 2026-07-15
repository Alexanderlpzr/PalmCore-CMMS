<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationEvent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Login::class, [LogAuthenticationEvent::class, 'handleLogin']);
        Event::listen(Failed::class, [LogAuthenticationEvent::class, 'handleFailed']);
        Event::listen(Logout::class, [LogAuthenticationEvent::class, 'handleLogout']);
    }
}
