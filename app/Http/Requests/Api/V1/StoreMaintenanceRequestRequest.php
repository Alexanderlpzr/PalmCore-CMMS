<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = CurrentTenant::id();

        return [
            'equipment_id' => [
                'required',
                'uuid',
                Rule::exists('equipment', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'request_type' => [
                'required',
                Rule::in(array_column(MaintenanceRequestType::cases(), 'value')),
            ],
            'priority' => [
                'required',
                Rule::in(array_column(MaintenanceRequestPriority::cases(), 'value')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'requested_due_date' => ['sometimes', 'nullable', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'equipment_id.required' => 'Debes indicar sobre qué equipo es este reporte.',
            'equipment_id.exists' => 'No encontramos ese equipo. Puede que haya sido eliminado.',
            'request_type.required' => 'Debes elegir un tipo de solicitud.',
            'request_type.in' => 'Ese tipo de solicitud no es válido.',
            'priority.required' => 'Debes elegir una prioridad.',
            'priority.in' => 'Esa prioridad no es válida.',
            'title.required' => 'Debes escribir un título breve del problema.',
            'title.max' => 'El título es demasiado largo (máximo 255 caracteres).',
            'description.required' => 'Debes describir el problema con más detalle.',
            'requested_due_date.after' => 'La fecha solicitada debe ser posterior a hoy.',
        ];
    }
}
