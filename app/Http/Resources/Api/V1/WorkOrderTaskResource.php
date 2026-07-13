<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'sort_order' => $this->sort_order,
            'title' => $this->title,
            'description' => $this->description,
            'estimated_minutes' => $this->estimated_minutes,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'skipped_reason' => $this->skipped_reason,

            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'completed_by' => $this->whenLoaded('completedBy', fn () => $this->completedBy?->name),
            'assigned_to' => $this->assigned_to,

            'checklist' => WorkOrderChecklistResultResource::collection(
                $this->whenLoaded('checklistResults')
            ),
        ];
    }
}
