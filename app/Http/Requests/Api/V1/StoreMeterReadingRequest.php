<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // No `gte` against the current value: a lower reading is a meter swap,
            // not a typo, and the service records it as such.
            'reading_value' => ['required', 'numeric', 'min:0'],
            'reading_unit' => ['sometimes', 'nullable', Rule::enum(MeterReadingUnit::class)],
            'recorded_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reading_value.required' => 'Ingresa la lectura del horómetro.',
            'reading_value.min' => 'Una lectura de horómetro no puede ser negativa.',
        ];
    }
}
