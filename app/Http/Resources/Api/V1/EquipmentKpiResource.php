<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentKpiResource extends JsonResource
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
            'period_months' => $this->period_months,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'availability_percentage' => $this->availability_percentage !== null ? (float) $this->availability_percentage : null,
            'mtbf_hours' => $this->mtbf_hours !== null ? (float) $this->mtbf_hours : null,
            'mttr_hours' => $this->mttr_hours !== null ? (float) $this->mttr_hours : null,
            'failure_count' => $this->failure_count,
            'downtime_hours' => $this->downtime_hours !== null ? (float) $this->downtime_hours : null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
