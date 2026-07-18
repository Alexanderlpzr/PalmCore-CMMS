<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\FailureConsequenceCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\FailureModeAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RCM-lite catalogue row: for a given equipment (or one of its components),
 * what failure mode it can have and what consequence that carries. When the
 * consequence is Hidden, failure_finding_plan_id points at the periodic
 * inspection task that reveals it — without one, the analysis flags a gap.
 */
#[Fillable([
    'tenant_id',
    'equipment_id',
    'equipment_component_id',
    'failure_mode',
    'consequence_category',
    'effect_description',
    'failure_finding_plan_id',
    'notes',
])]
class FailureModeAnalysis extends BaseModel
{
    /** @use HasFactory<FailureModeAnalysisFactory> */
    use HasFactory;

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function equipmentComponent(): BelongsTo
    {
        return $this->belongsTo(EquipmentComponent::class);
    }

    public function failureFindingPlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'failure_finding_plan_id');
    }

    public function isHidden(): bool
    {
        return $this->consequence_category === FailureConsequenceCategory::Hidden;
    }

    /** A hidden failure without a failure-finding task is an open RCM gap. */
    public function needsFailureFindingTask(): bool
    {
        return $this->isHidden() && $this->failure_finding_plan_id === null;
    }

    protected function casts(): array
    {
        return [
            'failure_mode' => FailureMode::class,
            'consequence_category' => FailureConsequenceCategory::class,
        ];
    }
}
