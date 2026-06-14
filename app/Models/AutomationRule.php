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
    public function isActionable(): bool
    {
        return $this->is_active && $this->mode !== AutomationMode::Disabled;
    }
}
