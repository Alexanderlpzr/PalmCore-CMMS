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
            'title' => $this->title,
            'description' => $this->description,
            'rejection_reason' => $this->rejection_reason,
            'requested_due_date' => $this->requested_due_date?->toDateString(),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->id,
                'code' => $this->equipment->code,
                'name' => $this->equipment->name,
            ] : null),
            'work_order' => $this->whenLoaded('workOrder', fn () => $this->workOrder ? [
                'id' => $this->workOrder->id,
                'work_order_number' => $this->workOrder->work_order_number,
                'status' => $this->workOrder->status?->value,
            ] : null),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'is_internal' => $c->is_internal,
                'user' => $c->relationLoaded('user') && $c->user ? [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                ] : null,
                'created_at' => $c->created_at->toISOString(),
            ])->values()),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
