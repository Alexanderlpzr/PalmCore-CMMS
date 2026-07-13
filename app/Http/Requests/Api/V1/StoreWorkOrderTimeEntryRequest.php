<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Maintenance\Enums\TimeLogActivityType;
use App\Http\Requests\Concerns\HasGpsPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderTimeEntryRequest extends FormRequest
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
            'started_at' => ['required', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:started_at'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'activity_type' => ['sometimes', 'nullable', Rule::enum(TimeLogActivityType::class)],
        ], $this->gpsRules());
    }
}
