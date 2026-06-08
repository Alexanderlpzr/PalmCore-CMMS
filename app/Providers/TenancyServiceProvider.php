<?php

namespace App\Providers;

use App\Infrastructure\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantResolver::class);
    }

    public function boot(): void
    {
        Route::aliasMiddleware('tenant', \App\Http\Middleware\ResolveTenant::class);
    }
}
