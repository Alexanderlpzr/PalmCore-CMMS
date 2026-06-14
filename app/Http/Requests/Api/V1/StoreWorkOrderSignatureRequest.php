<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Http\Requests\Concerns\HasGpsPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderSignatureRequest extends FormRequest
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
            'signature_type' => ['required', Rule::in(array_column(WorkOrderSignatureType::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ], $this->gpsRules());
    }
}
