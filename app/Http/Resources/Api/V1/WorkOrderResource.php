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
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->id,
                'code' => $this->equipment->code,
                'name' => $this->equipment->name,
            ] : null),
            'planned_start_at' => $this->planned_start_at?->toISOString(),
            'planned_end_at' => $this->planned_end_at?->toISOString(),
            'actual_start_at' => $this->actual_start_at?->toISOString(),
            'actual_end_at' => $this->actual_end_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'actual_cost_total' => $this->actual_cost_total !== null ? (float) $this->actual_cost_total : null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
