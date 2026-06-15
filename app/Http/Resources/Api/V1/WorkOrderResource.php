<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_number' => $this->work_order_number,
            'work_order_type' => $this->work_order_type?->value,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'failure_cause' => $this->failure_cause,
            'work_performed' => $this->work_performed,
            'root_cause' => $this->root_cause,
            'rejection_reason' => $this->rejection_reason,
            'equipment_stopped' => $this->equipment_stopped,
            'downtime_minutes' => $this->downtime_minutes,
            'planned_labor_hours' => $this->planned_labor_hours,
            'actual_labor_hours' => $this->actual_labor_hours,
            'estimated_cost' => $this->estimated_cost !== null ? (float) $this->estimated_cost : null,
            'actual_cost_labor' => $this->actual_cost_labor !== null ? (float) $this->actual_cost_labor : null,
            'actual_cost_parts' => $this->actual_cost_parts !== null ? (float) $this->actual_cost_parts : null,
            'actual_cost_external' => $this->actual_cost_external !== null ? (float) $this->actual_cost_external : null,
            'actual_cost_total' => $this->actual_cost_total !== null ? (float) $this->actual_cost_total : null,
            'currency_code' => $this->currency_code,
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->id,
                'code' => $this->equipment->code,
                'name' => $this->equipment->name,
            ] : null),
            'plant' => $this->whenLoaded('plant', fn () => $this->plant ? [
                'id' => $this->plant->id,
                'name' => $this->plant->name,
            ] : null),
            'area' => $this->whenLoaded('area', fn () => $this->area ? [
                'id' => $this->area->id,
                'name' => $this->area->name,
            ] : null),
            'technicians' => $this->whenLoaded('technicians', fn () => $this->technicians->map(fn ($t) => [
                'id' => $t->id,
                'role' => $t->role?->value,
                'planned_hours' => $t->planned_hours,
                'user' => $t->relationLoaded('user') && $t->user ? [
                    'id' => $t->user->id,
                    'name' => $t->user->name,
                ] : null,
            ])->values()),
            'parts' => $this->whenLoaded('parts', fn () => $this->parts->map(fn ($p) => [
                'id' => $p->id,
                'part_code' => $p->part_code,
                'description' => $p->description,
                'quantity' => (float) $p->quantity,
                'unit' => $p->unit,
                'unit_cost' => $p->unit_cost !== null ? (float) $p->unit_cost : null,
                'total_cost' => $p->total_cost !== null ? (float) $p->total_cost : null,
                'status' => $p->status?->value,
            ])->values()),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'is_internal' => $c->is_internal,
                'user' => $c->relationLoaded('user') && $c->user ? [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                ] : null,
                'created_at' => $c->created_at->toISOString(),
            ])->values()),
            'planned_start_at' => $this->planned_start_at?->toISOString(),
            'planned_end_at' => $this->planned_end_at?->toISOString(),
            'actual_start_at' => $this->actual_start_at?->toISOString(),
            'actual_end_at' => $this->actual_end_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'verified_at' => $this->verified_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
