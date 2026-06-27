<?php

namespace App\Filament\Platform\Pages;

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Models\Tenant;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PlatformDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.platform.dashboard';

    public function getViewData(): array
    {
        return [
            'totalTenants' => Tenant::count(),
            'activeTenants' => Tenant::where('subscription_status', SubscriptionStatus::Active)->count(),
            'trialTenants' => Tenant::where('subscription_status', SubscriptionStatus::Trial)->count(),
            'suspendedTenants' => Tenant::where('subscription_status', SubscriptionStatus::Suspended)->count(),
        ];
    }
}
