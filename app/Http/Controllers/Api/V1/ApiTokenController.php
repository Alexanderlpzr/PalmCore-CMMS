<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateTokenRequest;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class ApiTokenController extends Controller
{
    public function store(CreateTokenRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::once($credentials)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account is inactive.'], 403);
        }

        $tenant = Tenant::where('slug', $request->tenant_slug)->first();

        if (! $user->canAccessTenant($tenant)) {
            return response()->json(['message' => 'You do not have access to this tenant.'], 403);
        }

        $abilities = $request->abilities ?? ['work-orders.read', 'work-orders.write', 'equipment.read', 'maintenance-requests.read', 'maintenance-requests.write', 'inventory.read', 'plants.read', 'areas.read'];

        // Short-lived access token (1 hour) — stored in JS memory only
        $accessResult = $user->createToken(
            $request->token_name,
            $abilities,
            now()->addHour(),
        );
        $accessResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

        // Long-lived refresh token (7 days) — stored in HttpOnly cookie, never in JS
        $refreshResult = $user->createToken(
            $request->token_name.' [refresh]',
            ['token.refresh'],
            now()->addDays(7),
        );
        $refreshResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

        $refreshCookie = Cookie::make(
            name: 'fronda_refresh_token',
            value: $refreshResult->plainTextToken,
            minutes: 60 * 24 * 7,
            path: '/api/v1/auth',
            secure: app()->isProduction(),
            httpOnly: true,
            sameSite: 'Strict',
        );

        return response()->json([
            'token' => $accessResult->plainTextToken,
            'abilities' => $abilities,
            'expires_at' => now()->addHour()->toISOString(),
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'user' => ['id' => $user->id, 'name' => $user->name, 'is_super_admin' => (bool) $user->is_super_admin],
        ], 201)->withCookie($refreshCookie);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->find($tokenId);

        if (! $token) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        $token->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->select(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            ->latest()
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => json_decode($token->abilities ?? '["*"]', true),
                'last_used_at' => $token->last_used_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
            ]);

        return response()->json(['data' => $tokens]);
    }
}
