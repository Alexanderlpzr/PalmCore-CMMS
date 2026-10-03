<?php

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

/*
 * La API aplica lo mismo que la web: una empresa suspendida o con el plan vencido
 * consulta, pero no crea ni cambia nada. Antes la API no miraba el estado, y una
 * empresa suspendida seguía creando órdenes desde la app.
 */

/**
 * @param  array<string, mixed>  $tenantAttributes
 * @param  list<string>  $abilities
 * @return array{tenant: Tenant, user: User, token: string}
 */
function empresaConTokenDeApi(array $tenantAttributes = [], array $abilities = ['*']): array
{
    $tenant = Tenant::factory()->create($tenantAttributes);
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('app', $abilities);
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['tenant' => $tenant, 'user' => $user, 'token' => $token->plainTextToken];
}

/** @return array<string, string> */
function cabecerasDeApi(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

/** @return array<string, mixed> */
function ordenDeTrabajoDePrueba(Tenant $tenant): array
{
    return [
        'equipment_id' => Equipment::factory()->create(['tenant_id' => $tenant->id])->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Bomba fallando',
        'description' => 'Pérdida de presión.',
    ];
}

it('lets a suspended company read through the API', function (): void {
    ['token' => $token] = empresaConTokenDeApi(['subscription_status' => SubscriptionStatus::Suspended, 'is_active' => false]);

    $this->getJson('/api/v1/work-orders', cabecerasDeApi($token))->assertOk();
});

it('refuses writes from a suspended company and says why', function (): void {
    ['tenant' => $tenant, 'token' => $token] = empresaConTokenDeApi(['subscription_status' => SubscriptionStatus::Suspended, 'is_active' => false]);

    $this->postJson('/api/v1/work-orders', ordenDeTrabajoDePrueba($tenant), cabecerasDeApi($token))
        ->assertForbidden()
        ->assertJson([
            'code' => 'tenant_read_only',
            'message' => SubscriptionStatus::Suspended->writeRejectionMessage(),
        ]);

    expect(WorkOrder::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('treats an active company with an expired plan as read only, as the web does', function (): void {
    ['tenant' => $tenant, 'token' => $token] = empresaConTokenDeApi([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => now()->subDay(),
    ]);

    $this->postJson('/api/v1/work-orders', ordenDeTrabajoDePrueba($tenant), cabecerasDeApi($token))
        ->assertForbidden()
        ->assertJsonPath('message', SubscriptionStatus::ReadOnly->writeRejectionMessage());
});

it('still lets an active company write through the API', function (): void {
    ['tenant' => $tenant, 'token' => $token] = empresaConTokenDeApi(['subscription_status' => SubscriptionStatus::Active]);

    $this->postJson('/api/v1/work-orders', ordenDeTrabajoDePrueba($tenant), cabecerasDeApi($token))
        ->assertCreated();
});

it('lets a suspended company register its phone for push notices', function (): void {
    // No toca los datos de la planta, y darlo de baja es parte de cerrar sesión.
    ['token' => $token] = empresaConTokenDeApi(['subscription_status' => SubscriptionStatus::Suspended, 'is_active' => false]);

    $this->postJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/empresa-suspendida',
        'public_key' => base64_encode('clave-publica'),
        'auth_token' => base64_encode('clave-auth'),
        'content_encoding' => 'aes128gcm',
        'device_name' => 'Chrome Android',
    ], cabecerasDeApi($token))->assertNoContent();
});

it('refuses logging in to the app against an archived company instead of failing', function (): void {
    // La validación (`exists`) deja pasar a las archivadas y `first()` no las ve: daba 500.
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true, 'password' => 'Clave-De-Prueba-1']);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    $tenant->delete();

    $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'Clave-De-Prueba-1',
        'tenant_slug' => $tenant->slug,
        'token_name' => 'app',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes acceso a esta empresa.');
});

it('keeps refreshing the session of a suspended company, which stays read only', function (): void {
    // Antes la echaba de la app al cabo de una hora, aunque podía volver a iniciar sesión.
    ['tenant' => $tenant, 'user' => $user] = empresaConTokenDeApi(['subscription_status' => SubscriptionStatus::Suspended, 'is_active' => false]);
    $refresh = $user->createToken('app [refresh]', ['token.refresh'], now()->addDays(7));
    $refresh->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    $this->withCredentials()
        ->withUnencryptedCookie('fronda_refresh_token', $refresh->plainTextToken)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->assertJsonPath('tenant.id', $tenant->id);
});

it('does not refresh the session of an archived company', function (): void {
    ['tenant' => $tenant, 'user' => $user] = empresaConTokenDeApi();
    $refresh = $user->createToken('app [refresh]', ['token.refresh'], now()->addDays(7));
    $refresh->accessToken->forceFill(['tenant_id' => $tenant->id])->save();
    $tenant->delete();

    $this->withCredentials()
        ->withUnencryptedCookie('fronda_refresh_token', $refresh->plainTextToken)
        ->postJson('/api/v1/auth/refresh')
        ->assertForbidden();
});
