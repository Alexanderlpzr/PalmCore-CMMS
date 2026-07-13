<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_slug' => ['required', 'string', 'exists:tenants,slug'],
            'token_name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'in:equipment.read,equipment.write,work-orders.read,work-orders.write,maintenance-requests.read,maintenance-requests.write,maintenance-plans.read,inventory.read,inventory.write,reliability.read,reports.read,downtime.read,downtime.write,permits.read,permits.write,plants.read,plants.write,areas.read,alerts.read,alerts.write,*'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
