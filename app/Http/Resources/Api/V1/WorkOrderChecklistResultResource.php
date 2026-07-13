<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderChecklistResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            // The frozen definition — what the técnico was asked, and the tolerance
            // his reading is judged against. Never re-read from the plan.
            'label' => $this->label,
            'item_type' => $this->item_type?->value,
            'unit' => $this->unit,
            'expected_min' => $this->expected_min,
            'expected_max' => $this->expected_max,
            'expected_range_label' => $this->expectedRangeLabel(),
            'is_required' => $this->is_required,

            'value_boolean' => $this->value_boolean,
            'value_numeric' => $this->value_numeric,
            'value_text' => $this->value_text,
            'display_value' => $this->displayValue(),
            'is_answered' => $this->isAnswered(),
            'is_out_of_range' => $this->is_out_of_range,
            'deviation' => $this->deviation(),

            'photo_path' => $this->photo_path,
            'notes' => $this->notes,
            'recorded_at' => $this->recorded_at?->toISOString(),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
        ];
    }
}
