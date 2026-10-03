<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MobileAppModes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class TokenRefreshController extends Controller
{
    /**
     * Exchange the HttpOnly refresh cookie for a new short-lived access token.
     *
     * The refresh token is never exposed to JavaScript — it travels only via
     * the HttpOnly cookie, making it immune to XSS token theft.
     */
    public function store(Request $request): JsonResponse
    {
        $rawCookie = $request->cookies->get('fronda_refresh_token');

        if (! $rawCookie || ! str_contains($rawCookie, '|')) {
            return response()->json(['message' => 'No hay sesión activa.'], 401);
        }

        [$id, $tokenValue] = explode('|', $rawCookie, 2);
        $refreshPat = PersonalAccessToken::find($id);

        if (! $refreshPat || ! hash_equals(hash('sha256', $tokenValue), $refreshPat->token)) {
            return response()->json(['message' => 'Sesión inválida.'], 401);
        }

        if ($refreshPat->expires_at?->isPast()) {
            $refreshPat->delete();

            return response()->json(['message' => 'Sesión expirada. Inicia sesión nuevamente.'], 401)
                ->withoutCookie('fronda_refresh_token');
        }

        // `abilities` ya llega como arreglo (cast `json` del modelo). El json_decode()
        // que había aquí reventaba con un TypeError: renovar daba 500 siempre, y la app
        // móvil y /app pedían iniciar sesión otra vez al recargar o a la hora.
        if (! in_array('token.refresh', $refreshPat->abilities ?? [], true)) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        /** @var User $user */
        $user = $refreshPat->tokenable;

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Cuenta inactiva.'], 403);
        }

        // Una empresa suspendida sigue renovando: entra a consultar y la escritura la
        // frena ResolveApiTenant, como en la web. Antes la echaba de la app al cabo de
        // una hora, aunque podía volver a iniciar sesión. Una archivada no aparece.
        $tenant = Tenant::find($refreshPat->tenant_id);

        if (! $tenant) {
            return response()->json(['message' => 'Empresa no encontrada.'], 403);
        }

        // Revoke previous access tokens for this user+tenant to avoid accumulation
        PersonalAccessToken::where('tokenable_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->where('id', '!=', $refreshPat->id)
            ->whereJsonDoesntContain('abilities', 'token.refresh')
            ->delete();

        $accessResult = $user->createToken(
            'Fronda Mobile',
            // Las mismas que pide la app al entrar: sin las de asistencia, el vigilante
            // perdía la portería a la hora de haber iniciado sesión.
            MobileAppModes::TOKEN_ABILITIES,
            now()->addHour(),
        );
        $accessResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

        return response()->json([
            'token' => $accessResult->plainTextToken,
            'expires_at' => now()->addHour()->toISOString(),
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_super_admin' => (bool) $user->is_super_admin,
                'modes' => MobileAppModes::for($user, $tenant),
            ],
        ]);
    }

    /**
     * Revoke the refresh cookie and the current access token.
     */
    public function destroy(Request $request): JsonResponse
    {
        $rawCookie = $request->cookies->get('fronda_refresh_token');

        if ($rawCookie && str_contains($rawCookie, '|')) {
            [$id] = explode('|', $rawCookie, 2);
            PersonalAccessToken::find($id)?->delete();
        }

        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.'])
            ->withCookie(Cookie::forget('fronda_refresh_token', '/api/v1/auth'));
    }
}
