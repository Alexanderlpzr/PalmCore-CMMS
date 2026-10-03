<?php

use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/*
 * La app móvil y /app renuevan su token de una hora con la cookie de renovación, al
 * recargar y al vencer. Esa renovación daba 500 siempre: hacía json_decode() de las
 * habilidades del token, que el modelo ya entrega como arreglo.
 */

/** Un token de la persona en la empresa, como los que emite POST /api/v1/tokens. */
function tokenDeRenovacion(User $user, Tenant $tenant, array $abilities = ['token.refresh']): NewAccessToken
{
    $token = $user->createToken('app [refresh]', $abilities, now()->addDays(7));
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return $token;
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
});

it('renews the session of the app', function (): void {
    $this->withCredentials()
        ->withUnencryptedCookie('fronda_refresh_token', tokenDeRenovacion($this->user, $this->tenant)->plainTextToken)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->assertJsonStructure(['token', 'expires_at', 'tenant', 'user'])
        ->assertJsonPath('tenant.id', $this->tenant->id);
});

it('does not take an ordinary access token as a refresh token', function (): void {
    // Ni siquiera uno con todas las habilidades: solo cuenta `token.refresh`.
    $this->withCredentials()
        ->withUnencryptedCookie('fronda_refresh_token', tokenDeRenovacion($this->user, $this->tenant, ['*'])->plainTextToken)
        ->postJson('/api/v1/auth/refresh')
        ->assertUnauthorized();
});
