<?php

namespace App\Http\Requests\Api\V1;

use App\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryTransactionRequest extends FormRequest
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
            'type' => ['required', Rule::in(['entry', 'exit'])],
            'warehouse_id' => [
                'required',
                'uuid',
                Rule::exists('warehouses', 'id')
                    ->where('tenant_id', $tenantId),
            ],
            'spare_part_id' => [
                'required',
                'uuid',
                Rule::exists('spare_parts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
