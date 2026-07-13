<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeterReadingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'reading_value' => $this->reading_value,
            'reading_unit' => $this->reading_unit?->value,
            // The dial can go backwards; the accumulated total never does.
            'previous_value' => $this->previous_value,
            'delta' => $this->delta,
            'accumulated_value' => $this->accumulated_value,
            'is_reset' => $this->is_reset,
            'notes' => $this->notes,
            'recorded_at' => $this->recorded_at?->toISOString(),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
        ];
    }
}
