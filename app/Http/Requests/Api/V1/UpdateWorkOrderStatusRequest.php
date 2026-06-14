<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Http\Requests\Concerns\HasGpsPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderStatusRequest extends FormRequest
{
    use HasGpsPayload;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'status' => [
                'required',
                Rule::in(array_column(WorkOrderStatus::cases(), 'value')),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
        ], $this->gpsRules());
    }
}
