<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuditServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    AuditServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TenancyServiceProvider::class,
];
