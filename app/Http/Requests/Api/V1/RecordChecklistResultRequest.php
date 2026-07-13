<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `value` is deliberately loosely typed: its meaning depends on the item's frozen
 * `item_type` (boolean / numeric / text), which the service resolves. Typing it
 * here would duplicate that decision in a second place.
 */
class RecordChecklistResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'value' => ['present'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'photo' => ['sometimes', 'nullable', 'file', 'image', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'value.present' => 'Debes registrar un valor para este ítem.',
            'photo.image' => 'La evidencia debe ser una imagen.',
            'photo.max' => 'La imagen no puede superar los 10 MB.',
        ];
    }
}
