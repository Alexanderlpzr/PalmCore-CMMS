<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DowntimeEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->id,
                'code' => $this->equipment->code,
                'name' => $this->equipment->name,
            ] : null),
            'work_order_number' => $this->work_order_number,
            'cause_type' => $this->cause_type?->value,
            'was_planned' => $this->was_planned,
            'notes' => $this->notes,
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
