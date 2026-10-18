<?php

namespace App\Models;

use App\Domain\Automation\Enums\AutomationEventType;
use App\Domain\Automation\Enums\AutomationMode;
use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'name',
        'event_type',
        'mode',
        'is_active',
        'configuration',
        'last_executed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AutomationEventType::class,
            'mode' => AutomationMode::class,
            'is_active' => 'boolean',
            'configuration' => 'array',
            'last_executed_at' => 'datetime',
        ];
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AutomationRuleExecution::class, 'rule_id');
    }

    /** True only when the rule can be acted upon. */
    /**
     * La alerta de repuestos por reponer, encendida en cada empresa: sin ella el almacén
     * se entera de que algo se acabó cuando lo piden. Si alguien la borró a propósito,
     * no se vuelve a crear.
     */
    public static function ensureStockLowRule(string $tenantId): void
    {
        $exists = self::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('event_type', AutomationEventType::StockLow->value)
            ->exists();

        if ($exists) {
            return;
        }

        self::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => 'Repuestos por reponer',
            'event_type' => AutomationEventType::StockLow,
            'mode' => AutomationMode::NotifyOnly,
            'is_active' => true,
        ]);
    }

    public function isActionable(): bool
    {
        return $this->is_active && $this->mode !== AutomationMode::Disabled;
    }
}
