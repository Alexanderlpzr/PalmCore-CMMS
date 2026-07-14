<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Assets\Enums\StoppageCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A4 — el Tipo I después del diagnóstico.
 *
 * «Programado» no se acepta: eso lo decide el origen del paro, no el hallazgo. Si
 * se pudiera diagnosticar como programada, cualquier falla incómoda saldría del
 * MTBF con un clic.
 */
class ClassifyDowntimeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stoppage_category' => [
                'required',
                Rule::enum(StoppageCategory::class)->except(StoppageCategory::Planned),
            ],
            // Tipo II — la causa concreta, en las palabras del técnico.
            'stoppage_cause' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'stoppage_category.required' => 'Debes indicar el Tipo I diagnosticado.',
            'stoppage_category.enum' => 'Ese Tipo I no es un diagnóstico válido.',
        ];
    }
}
