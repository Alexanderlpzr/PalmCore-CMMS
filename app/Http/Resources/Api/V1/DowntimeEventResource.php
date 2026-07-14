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
            'plant_id' => $this->plant_id,
            'is_plant_wide' => $this->isPlantWide(),
            'work_order_id' => $this->work_order_id,
            'work_order_number' => $this->work_order_number,

            'cause_type' => $this->cause_type?->value,
            // Tipo I × Tipo II — the taxonomy the plant already uses on paper.
            'stoppage_category' => $this->stoppage_category?->value,
            'stoppage_category_label' => $this->stoppage_category?->label(),
            'stoppage_category_color' => $this->stoppage_category?->color(),
            'is_maintenance_responsibility' => $this->stoppage_category?->isMaintenanceResponsibility(),
            'stoppage_cause' => $this->stoppage_cause,

            'was_planned' => $this->was_planned,
            'affects_production' => $this->affects_production,
            'source' => $this->source,
            'notes' => $this->notes,

            // A5 — la firma de producción sobre las horas.
            'confirmation_status' => $this->confirmation_status?->value,
            'confirmation_status_label' => $this->confirmation_status?->label(),
            'confirmation_status_color' => $this->confirmation_status?->color(),
            'requires_confirmation' => $this->requiresProductionConfirmation(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'confirmation_notes' => $this->confirmation_notes,
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn () => $this->confirmedBy ? [
                'id' => $this->confirmedBy->id,
                'name' => $this->confirmedBy->name,
            ] : null),

            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'is_ongoing' => $this->isOngoing(),
            'duration_minutes' => $this->elapsedMinutes(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
