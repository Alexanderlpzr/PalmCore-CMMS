<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        PushSubscription::withoutGlobalScopes()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'endpoint' => $request->validated('endpoint'),
            ],
            [
                'tenant_id' => $request->user()->currentAccessToken()->tenant_id,
                'public_key' => $request->validated('public_key'),
                'auth_token' => $request->validated('auth_token'),
                'content_encoding' => $request->validated('content_encoding') ?? 'aesgcm',
                'device_name' => $request->validated('device_name'),
                'last_used_at' => now(),
                'is_active' => true,
            ]
        );

        return response()->json(null, 204);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string']]);

        PushSubscription::withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->where('endpoint', $request->input('endpoint'))
            ->update(['is_active' => false]);

        return response()->json(null, 204);
    }
}
