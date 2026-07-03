<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\WorkOrderAttachmentType;
use App\Http\Requests\Concerns\HasGpsPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderMediaRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,heic,pdf,mp4'],
            'attachment_type' => ['required', Rule::in(array_column(WorkOrderAttachmentType::cases(), 'value'))],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], $this->gpsRules());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Debes adjuntar un archivo.',
            'file.max' => 'El archivo es demasiado pesado (máximo 20 MB).',
            'file.mimes' => 'Ese tipo de archivo no está permitido. Usa foto, video o PDF.',
            'attachment_type.required' => 'Debes indicar qué tipo de archivo es.',
            'attachment_type.in' => 'Ese tipo de archivo no es válido.',
            'caption.max' => 'La descripción es demasiado larga (máximo 500 caracteres).',
        ];
    }
}
