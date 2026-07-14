<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A5 — producción no está de acuerdo con las horas, y tiene que decir por qué.
 *
 * Una disputa sin motivo no le sirve a nadie: el paro seguiría contando igual y
 * nadie sabría qué revisar.
 */
class DisputeDowntimeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Para disputar un paro hay que decir por qué.',
            'reason.min' => 'El motivo de la disputa es demasiado corto.',
        ];
    }
}
