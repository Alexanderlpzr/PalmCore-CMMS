<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:32767'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'La tarea necesita un título.',
        ];
    }
}
