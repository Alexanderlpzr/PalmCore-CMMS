<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderCommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'user_id' => $this->user_id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
