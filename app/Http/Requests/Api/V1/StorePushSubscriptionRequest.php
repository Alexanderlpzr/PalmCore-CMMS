<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url', 'max:2048'],
            'public_key' => ['required', 'string', 'max:512'],
            'auth_token' => ['required', 'string', 'max:512'],
            'content_encoding' => ['sometimes', 'nullable', 'string', 'in:aesgcm,aes128gcm'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
