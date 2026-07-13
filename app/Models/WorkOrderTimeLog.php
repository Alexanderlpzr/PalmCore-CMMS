<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\TimeLogActivityType;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderTimeLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'work_order_id',
    'user_id',
    'started_at',
    'ended_at',
    'hours',
    'activity_type',
    'description',
])]
class WorkOrderTimeLog extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WorkOrderTimeLogFactory> */
    use HasFactory;

    use HasUuids;

    // No soft deletes — audit log

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** Was the técnico working, or waiting? Unknown when nobody ever said. */
    public function isWrenchTime(): ?bool
    {
        return $this->activity_type?->isWrenchTime();
    }

    public function computedHours(): float
    {
        if ($this->hours !== null) {
            return (float) $this->hours;
        }

        if ($this->ended_at === null) {
            return 0.0;
        }

        return round(abs($this->ended_at->diffInMinutes($this->started_at)) / 60, 2);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'activity_type' => TimeLogActivityType::class,
        ];
    }
}
