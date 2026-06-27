<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentComponentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'part_number' => $this->part_number,
            'criticality' => $this->criticality?->value,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'worked_hours' => $this->worked_hours,
            'useful_life_hours' => $this->useful_life_hours,
            'notes' => $this->notes,
            'children' => EquipmentComponentResource::collection($this->whenLoaded('children')),
            'children_count' => $this->whenCounted('children'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
