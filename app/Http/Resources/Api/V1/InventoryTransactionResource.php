<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'type' => $this->type,
            'warehouse_id' => $this->warehouse_id,
            'spare_part_id' => $this->spare_part_id,
            'spare_part_code' => $this->spare_part_code_snapshot,
            'spare_part_name' => $this->spare_part_name_snapshot,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => $this->total_cost !== null ? (float) $this->total_cost : null,
            'previous_stock' => (float) $this->previous_stock,
            'new_stock' => (float) $this->new_stock,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'performed_by' => $this->performed_by,
            'performed_at' => $this->performed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
