<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class WorkOrderAttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'attachment_type' => $this->attachment_type?->value,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'caption' => $this->caption,
            'url' => Storage::disk(config('filesystems.default', 'public'))->url($this->file_path),
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
