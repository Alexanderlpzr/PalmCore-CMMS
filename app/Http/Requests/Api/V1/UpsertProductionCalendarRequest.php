<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpsertProductionCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1', 'max:62'],
            'days.*.calendar_date' => ['required', 'date_format:Y-m-d'],
            // Zero is legitimate: a día the plant was never meant to run.
            'days.*.programmed_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'days.*.notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'days.*.programmed_hours.max' => 'Un día no puede tener más de 24 horas programadas.',
        ];
    }
}
