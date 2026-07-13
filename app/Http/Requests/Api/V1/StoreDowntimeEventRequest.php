<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Assets\Enums\StoppageCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDowntimeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // One of the two is required — the service enforces that a paro names
            // either the equipment that stopped or the plant that stopped.
            'equipment_id' => ['sometimes', 'nullable', 'uuid', 'exists:equipment,id'],
            'plant_id' => ['required_without:equipment_id', 'nullable', 'uuid', 'exists:plants,id'],

            'stoppage_category' => ['required', Rule::enum(StoppageCategory::class)],
            'stoppage_cause' => ['sometimes', 'nullable', 'string', 'max:120'],

            'started_at' => ['sometimes', 'nullable', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:started_at'],

            'affects_production' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reported_by' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'plant_id.required_without' => 'Indica el equipo afectado o, si es un paro de planta, la planta.',
            'stoppage_category.required' => 'Un paro debe clasificarse (Tipo I).',
            'ended_at.after_or_equal' => 'Un paro no puede terminar antes de haber empezado.',
        ];
    }
}
