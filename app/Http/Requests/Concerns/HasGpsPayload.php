<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait HasGpsPayload
{
    /** @return array<string, mixed[]> */
    protected function gpsRules(): array
    {
        return [
            'gps' => ['sometimes', 'nullable', 'array'],
            'gps.latitude' => ['required_with:gps', 'numeric', 'between:-90,90'],
            'gps.longitude' => ['required_with:gps', 'numeric', 'between:-180,180'],
            'gps.accuracy' => ['required_with:gps', 'numeric', 'min:0', 'max:99999'],
            'gps.source' => ['sometimes', 'nullable', Rule::in(['gps', 'network', 'unknown'])],
            'gps.gps_timestamp' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
