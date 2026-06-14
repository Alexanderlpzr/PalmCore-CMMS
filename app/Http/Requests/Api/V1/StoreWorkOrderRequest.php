<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderRequest extends FormRequest
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
            'work_order_type' => [
                'required',
                Rule::in(array_column(WorkOrderType::cases(), 'value')),
            ],
            'priority' => [
                'required',
                Rule::in(array_column(WorkOrderPriority::cases(), 'value')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'equipment_stopped' => ['sometimes', 'boolean'],
            'planned_start_at' => ['sometimes', 'nullable', 'date'],
            'planned_end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:planned_start_at'],
            'plant_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('plants', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'area_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('areas', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
