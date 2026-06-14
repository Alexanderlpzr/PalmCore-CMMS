<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SparePartResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category_type' => $this->category_type?->value,
            'criticality' => $this->criticality?->value,
            'abc_classification' => $this->abc_classification?->value,
            'unit' => $this->unit,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'is_active' => $this->is_active,
            'manufacturer' => $this->whenLoaded('manufacturer', fn () => $this->manufacturer ? [
                'id' => $this->manufacturer->id,
                'name' => $this->manufacturer->name,
            ] : null),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
