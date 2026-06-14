<?php

namespace App\Domain\Automation\Evaluators;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Notifications\LowStockNotification;
use App\Events\InventoryLowStockDetected;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Support\Collection;

class InventoryRuleEvaluator
{
    public function __construct(private readonly AlertService $alertService) {}

    public function evaluate(AutomationRule $rule): void
    {
        // Find spare parts below reorder_point for this tenant.
        // withSum pre-loads total stock so isBelowReorderPoint() reads an attribute, not a query.
        $spareParts = SparePart::withoutGlobalScopes()
            ->where('spare_parts.tenant_id', $rule->tenant_id)
            ->where('spare_parts.is_active', true)
            ->whereNull('spare_parts.deleted_at')
            ->whereNotNull('spare_parts.reorder_point')
            ->whereHas('warehouseStock', fn ($q) => $q->withoutGlobalScopes())
            ->withSum(['warehouseStock' => fn ($q) => $q->withoutGlobalScopes()], 'current_stock')
            ->get()
            ->filter(fn (SparePart $sp) => $sp->isBelowReorderPoint());

        foreach ($spareParts as $sparePart) {
            $this->processLowStock($rule, $sparePart);
        }
    }

    private function processLowStock(AutomationRule $rule, SparePart $sparePart): void
    {
        // Weekly deduplication — low stock alerts re-fire each week until resolved
        $windowKey = now()->format('Y-W');
        $action = "notified_low_stock_{$windowKey}";

        $alreadyActed = AutomationRuleExecution::where([
            'rule_id' => $rule->id,
            'entity_type' => 'spare_part',
            'entity_id' => $sparePart->id,
            'action_taken' => $action,
        ])->exists();

        if ($alreadyActed) {
            return;
        }

        // Notify warehouse managers for this tenant
        $managers = $this->warehouseManagers($rule->tenant_id);

        foreach ($managers as $manager) {
            $manager->notify(new LowStockNotification($sparePart));
        }

        $this->alertService->create(new CreateAlertData(
            tenantId: $rule->tenant_id,
            severity: AlertSeverity::Warning,
            category: AlertCategory::Inventory,
            title: "Stock bajo: {$sparePart->code} — {$sparePart->name}",
            message: "Stock disponible por debajo del punto de reorden ({$sparePart->reorder_point} unidades).",
            entityType: 'spare_part',
            entityId: $sparePart->id,
            metadata: [
                'spare_part_code' => $sparePart->code,
                'reorder_point' => $sparePart->reorder_point,
            ],
        ));

        $this->record($rule, 'spare_part', $sparePart->id, $action, [
            'spare_part_code' => $sparePart->code,
            'reorder_point' => $sparePart->reorder_point,
        ]);

        event(new InventoryLowStockDetected(
            sparePart: $sparePart,
            currentStock: $sparePart->totalStock(),
            reorderPoint: (float) $sparePart->reorder_point,
        ));
    }

    private function record(AutomationRule $rule, string $entityType, string $entityId, string $action, array $metadata = []): void
    {
        AutomationRuleExecution::firstOrCreate(
            [
                'rule_id' => $rule->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action_taken' => $action,
            ],
            [
                'metadata' => $metadata ?: null,
                'executed_at' => now(),
            ],
        );
    }

    /** Users who have the warehouse management role for this tenant. */
    private function warehouseManagers(string $tenantId): Collection
    {
        return User::withoutGlobalScopes()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'like', '%almac%')
                ->orWhere('name', 'like', '%warehouse%')
                ->orWhere('name', 'like', '%inventar%')
                ->orWhere('name', 'Admin'))
            ->get();
    }
}
