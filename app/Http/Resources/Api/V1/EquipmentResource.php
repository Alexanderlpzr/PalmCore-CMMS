<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'notes' => $this->notes,
            'status' => $this->status?->value,
            'criticality' => $this->criticality?->value,
            'priority' => $this->priority?->value,
            'is_active' => $this->is_active,
            'installation_date' => $this->installation_date?->toDateString(),
            'plant' => $this->whenLoaded('plant', fn () => [
                'id' => $this->plant->id,
                'code' => $this->plant->code,
                'name' => $this->plant->name,
            ]),
            'area' => $this->whenLoaded('area', fn () => $this->area ? [
                'id' => $this->area->id,
                'code' => $this->area->code,
                'name' => $this->area->name,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
