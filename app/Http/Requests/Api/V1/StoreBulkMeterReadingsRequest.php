<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBulkMeterReadingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'readings' => ['required', 'array', 'min:1', 'max:200'],
            'readings.*.equipment_id' => ['required', 'uuid', 'exists:equipment,id'],
            'readings.*.reading_value' => ['required', 'numeric', 'min:0'],
            'readings.*.recorded_at' => ['sometimes', 'nullable', 'date'],
            'readings.*.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'readings.required' => 'La ronda no trae ninguna lectura.',
        ];
    }
}
