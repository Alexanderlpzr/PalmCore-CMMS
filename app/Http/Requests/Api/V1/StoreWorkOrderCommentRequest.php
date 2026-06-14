<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\HasGpsPayload;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ], $this->gpsRules());
    }
}
