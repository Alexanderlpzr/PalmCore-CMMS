<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Http\Requests\Concerns\HasGpsPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderStatusRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in(array_column(WorkOrderStatus::cases(), 'value')),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
            // Completion Experience — only meaningful when transitioning to
            // "completed", but validated generically since these are the
            // WorkOrder's own existing columns, not new ones.
            'work_performed' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'failure_cause' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'root_cause' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], $this->gpsRules());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Debes indicar el nuevo estado de la orden de trabajo.',
            'status.in' => 'Ese estado no es válido.',
            'work_performed.max' => 'El resultado obtenido es demasiado largo (máximo 5000 caracteres).',
            'failure_cause.max' => 'La causa de falla es demasiado larga (máximo 2000 caracteres).',
            'root_cause.max' => 'La causa raíz es demasiado larga (máximo 2000 caracteres).',
        ];
    }
}
