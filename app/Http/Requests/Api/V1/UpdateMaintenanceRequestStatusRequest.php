<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(array_column(MaintenanceRequestStatus::cases(), 'value')),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
