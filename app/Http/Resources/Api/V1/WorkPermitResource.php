<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkPermitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'permit_number' => $this->permit_number,
            'permit_type' => $this->permit_type->value,
            'permit_label' => $this->permit_type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'hazards' => $this->hazards,
            'controls' => $this->controls,
            'isolation_points' => $this->isolation_points ?? [],
            'valid_from' => $this->valid_from->toISOString(),
            'valid_until' => $this->valid_until->toISOString(),
            'is_expired' => $this->isExpired(),
            // Lo único que le importa al técnico parado frente a la máquina:
            // ¿este papel me autoriza a trabajar ahora mismo?
            'authorizes_work_now' => $this->authorizesWorkAt(),
            'issued_by' => $this->whenLoaded('issuedBy', fn () => $this->issuedBy?->name),
            'accepted_by' => $this->whenLoaded('acceptedBy', fn () => $this->acceptedBy?->name),
            'accepted_at' => $this->accepted_at?->toISOString(),
        ];
    }
}
