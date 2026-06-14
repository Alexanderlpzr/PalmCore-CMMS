<?php

namespace App\Filament\Widgets\Dashboard;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class WelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.welcome';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $tenantId = Filament::getTenant()?->id;

        $openWorkOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [WorkOrderStatus::Closed->value, WorkOrderStatus::Cancelled->value])
            ->count();

        $inProgressWorkOrders = WorkOrder::where('tenant_id', $tenantId)
            ->where('status', WorkOrderStatus::InProgress->value)
            ->count();

        $pendingRequests = MaintenanceRequest::where('tenant_id', $tenantId)
            ->whereIn('status', ['submitted', 'under_review'])
            ->count();

        $activeEquipment = Equipment::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        return [
            'userName' => auth()->user()?->name ?? 'Usuario',
            'tenantName' => Filament::getTenant()?->name ?? 'Fronda CMMS',
            'openWorkOrders' => $openWorkOrders,
            'inProgressWorkOrders' => $inProgressWorkOrders,
            'pendingRequests' => $pendingRequests,
            'activeEquipment' => $activeEquipment,
        ];
    }
}
