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
}
