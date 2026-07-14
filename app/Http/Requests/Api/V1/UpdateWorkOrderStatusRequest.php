<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\FailureMode;
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
            'failure_mode' => ['sometimes', 'nullable', Rule::enum(FailureMode::class)],
            // A4 — el Tipo I que el técnico solo puede afinar después de abrir la
            // máquina. Se propaga al paro que esta OT abrió; «programado» no se
            // diagnostica, así que no se acepta aquí.
            'diagnosed_stoppage_category' => [
                'sometimes',
                'nullable',
                Rule::enum(StoppageCategory::class)->except(StoppageCategory::Planned),
            ],
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
            'failure_mode.enum' => 'Ese modo de falla no es válido.',
            'diagnosed_stoppage_category.enum' => 'Ese Tipo I no es un diagnóstico válido.',
        ];
    }
}
