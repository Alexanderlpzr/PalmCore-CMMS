<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A5 — la firma del jefe de turno.
 *
 * Confirmar no exige explicación; disputar sí. Estar de acuerdo con las horas es
 * el caso normal, y pedirle un texto a quien solo va a decir «sí» es la forma más
 * rápida de que la firma se vuelva un trámite que nadie hace.
 */
class ConfirmDowntimeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
