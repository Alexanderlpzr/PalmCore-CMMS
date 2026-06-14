<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'request_type' => $this->request_type?->value,
            'priority' => $this->priority?->value,
            'status' => $this->status?->value,
            'description' => $this->description,
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->id,
                'code' => $this->equipment->code,
                'name' => $this->equipment->name,
            ] : null),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
