<?php

use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * Sprint PLATFORM-1 — Platform / tenant security audit.
 *
 * Covers:
 *  1. Tenant admin cannot access /platform (403 via EnsureSuperAdmin).
 *  2. Super admin can access /platform (200/redirect).
 *  3. Tenant A user cannot see Tenant B's work orders via the API.
 *  4. Inactive super admin cannot access /platform (403).
 *  5. Tenant admin cannot start impersonation (403).
 *  6. TenantScope is applied: a tenant user querying models only sees own records.
 */

// ── Helpers ───────────────────────────────────────────────────────────────────

function auditSuperAdmin(bool $active = true): User
{
    // fresh() ensures all DB-default columns (e.g. deleted_at) are loaded so
    // preventAccessingMissingAttributes does not throw inside Filament middleware.
    return User::factory()->create(['is_super_admin' => true, 'is_active' => $active])->fresh();
}

function auditTenantUser(Tenant $tenant, array $attributes = []): array
{
    $user = User::factory()->create(array_merge(['is_active' => true, 'is_super_admin' => false], $attributes))->fresh();
    $user->tenants()->attach($tenant->id, ['is_primary_tenant' => true, 'joined_at' => now()]);

    $tokenResult = $user->createToken('audit-token', ['*']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['user' => $user, 'token' => $tokenResult->plainTextToken];
}

// ── 1. Tenant admin cannot access /platform ───────────────────────────────────

it('returns 403 when a tenant admin tries to access the platform panel', function () {
    $tenant = Tenant::factory()->create();
    ['user' => $user] = auditTenantUser($tenant);

    $this->actingAs($user)
        ->get('/platform')
        ->assertForbidden();
});

// ── 2. Super admin can access /platform ───────────────────────────────────────

it('allows an active super admin to reach the platform panel', function () {
    $admin = auditSuperAdmin();

    $this->actingAs($admin)
        ->get('/platform')
        ->assertRedirectContains('/platform');
});

// ── 3. Tenant A user cannot see Tenant B's work orders via the API ────────────

it('a tenant user cannot read work orders belonging to another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    ['token' => $tokenA] = auditTenantUser($tenantA);

    // Work order created in tenant B's scope
    CurrentTenant::set($tenantB);
    $woB = WorkOrder::factory()->create(['tenant_id' => $tenantB->id]);
    CurrentTenant::clear();

    $this->getJson("/api/v1/work-orders/{$woB->id}", [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertNotFound();
});

// ── 4. Inactive super admin cannot access /platform ───────────────────────────

it('returns 403 for an inactive super admin attempting to access /platform', function () {
    $inactive = auditSuperAdmin(active: false);

    $this->actingAs($inactive)
        ->get('/platform')
        ->assertForbidden();
});

// ── 5. Tenant admin cannot start impersonation ────────────────────────────────

it('forbids a tenant admin from initiating impersonation', function () {
    $tenant = Tenant::factory()->create();
    ['user' => $attacker] = auditTenantUser($tenant);
    ['user' => $victim] = auditTenantUser($tenant);

    $this->actingAs($attacker)
        ->post(route('impersonation.start', $victim))
        ->assertForbidden();
});

// ── 6. TenantScope isolates model queries per tenant ─────────────────────────

it('applies TenantScope so a tenant user only sees their own records', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    ['token' => $tokenA] = auditTenantUser($tenantA);

    // Create equipment in each tenant directly (bypassing scope)
    $eqA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $eqB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->getJson('/api/v1/equipment', [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($eqA->id)
        ->and($ids)->not->toContain($eqB->id);
});
