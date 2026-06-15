<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'is_active' => $this->is_active,
            'items_count' => (int) ($this->items_count ?? 0),
            'total_inventory_value' => (float) ($this->total_inventory_value ?? 0),
            'low_stock_count' => (int) ($this->low_stock_count ?? 0),
            'stock' => $this->whenLoaded('stock', fn () => $this->stock->map(fn ($s) => [
                'id' => $s->id,
                'current_stock' => (float) $s->current_stock,
                'reserved_stock' => (float) $s->reserved_stock,
                'average_unit_cost' => $s->average_unit_cost !== null ? (float) $s->average_unit_cost : null,
                'bin_location' => $s->bin_location,
                'last_counted_at' => $s->last_counted_at?->toISOString(),
                'spare_part' => $s->relationLoaded('sparePart') && $s->sparePart ? [
                    'id' => $s->sparePart->id,
                    'code' => $s->sparePart->code,
                    'name' => $s->sparePart->name,
                    'unit' => $s->sparePart->unit?->value,
                    'minimum_stock' => $s->sparePart->minimum_stock !== null ? (float) $s->sparePart->minimum_stock : null,
                    'is_below_minimum' => $s->sparePart->minimum_stock !== null
                        && (float) $s->current_stock < (float) $s->sparePart->minimum_stock,
                ] : null,
            ])->values()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
