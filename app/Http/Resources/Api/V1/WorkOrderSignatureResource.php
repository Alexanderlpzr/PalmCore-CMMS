<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderSignatureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'signature_type' => $this->signature_type?->value,
            'user_id' => $this->user_id,
            'signed_at' => $this->signed_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
