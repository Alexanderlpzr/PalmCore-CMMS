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
            // Optional for backward compatibility with metadata-only callers, but the
            // mobile signature pad always sends it — see WorkOrderService::addSignature().
            'signature_image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:png', 'max:2048'],
        ], $this->gpsRules());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'signature_type.required' => 'Debes indicar qué tipo de firma es (técnico o supervisor).',
            'signature_type.in' => 'Ese tipo de firma no es válido.',
            'notes.max' => 'Las notas son demasiado largas (máximo 1000 caracteres).',
            'signature_image.image' => 'El archivo de la firma no es una imagen válida.',
            'signature_image.mimes' => 'La firma debe guardarse como imagen PNG.',
            'signature_image.max' => 'La imagen de la firma es demasiado pesada.',
        ];
    }
}
