<?php

use App\Models\ImpersonationLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ImpersonationService;

/**
 * Sprint ADMIN-2 — secure Super Admin impersonation.
 */
function superAdmin(): User
{
    return User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
}

function tenantUserIn(Tenant $tenant, array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['is_active' => true, 'is_super_admin' => false], $attributes));
    $user->tenants()->attach($tenant->id, ['is_primary_tenant' => true, 'joined_at' => now()]);

    return $user;
}

// ── Start ─────────────────────────────────────────────────────────────────────

it('lets a super admin start impersonation and records a full audit entry', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create();
    $target = tenantUserIn($tenant);

    $this->actingAs($admin)
        ->post(route('impersonation.start', $target), ['reason' => 'Soporte ticket #42'])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($target);

    $log = ImpersonationLog::sole();
    expect($log->impersonator_id)->toBe($admin->getKey())
        ->and($log->impersonated_user_id)->toBe($target->getKey())
        ->and($log->tenant_id)->toBe($tenant->getKey())
        ->and($log->reason)->toBe('Soporte ticket #42')
        ->and($log->started_at)->not->toBeNull()
        ->and($log->ip_address)->not->toBeNull()
        ->and($log->ended_at)->toBeNull();

    expect(session(ImpersonationService::SESSION_KEY))->not->toBeNull();
});

it('stores impersonation context in the session for the banner', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create(['name' => 'Empresa XYZ']);
    $target = tenantUserIn($tenant, ['name' => 'Juan Pérez']);

    $this->actingAs($admin)->post(route('impersonation.start', $target));

    $context = session(ImpersonationService::SESSION_KEY);
    expect($context['impersonated_name'])->toBe('Juan Pérez')
        ->and($context['tenant_name'])->toBe('Empresa XYZ')
        ->and($context['impersonator_id'])->toBe($admin->getKey());
});

// ── Stop / restore ────────────────────────────────────────────────────────────

it('restores the original super admin and closes the audit on leave', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create();
    $target = tenantUserIn($tenant);

    $this->actingAs($admin)->post(route('impersonation.start', $target));
    $this->assertAuthenticatedAs($target);

    $this->post(route('impersonation.leave'))->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
    expect(session(ImpersonationService::SESSION_KEY))->toBeNull();

    $log = ImpersonationLog::sole();
    expect($log->ended_at)->not->toBeNull()
        ->and($log->duration_seconds)->toBeGreaterThanOrEqual(0);
});

it('leaving without an active impersonation is a harmless no-op', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->post(route('impersonation.leave'))->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
    expect(ImpersonationLog::count())->toBe(0);
});

// ── Authorization / security ──────────────────────────────────────────────────

it('forbids a regular user from impersonating anyone', function () {
    $tenant = Tenant::factory()->create();
    $regular = tenantUserIn($tenant);
    $target = tenantUserIn($tenant);

    $this->actingAs($regular)
        ->post(route('impersonation.start', $target))
        ->assertForbidden();

    $this->assertAuthenticatedAs($regular);
    expect(ImpersonationLog::count())->toBe(0);
});

it('never allows impersonating another super admin', function () {
    $admin = superAdmin();
    $otherAdmin = superAdmin();

    $this->actingAs($admin)
        ->post(route('impersonation.start', $otherAdmin))
        ->assertForbidden();

    expect(ImpersonationLog::count())->toBe(0);
});

it('forbids impersonating yourself', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->post(route('impersonation.start', $admin))
        ->assertForbidden();

    expect(ImpersonationLog::count())->toBe(0);
});

it('forbids impersonating an inactive user', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create();
    $inactive = tenantUserIn($tenant, ['is_active' => false]);

    $this->actingAs($admin)
        ->post(route('impersonation.start', $inactive))
        ->assertForbidden();

    expect(ImpersonationLog::count())->toBe(0);
});

it('forbids nested impersonation', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create();
    $first = tenantUserIn($tenant);
    $second = tenantUserIn($tenant);

    $this->actingAs($admin)->post(route('impersonation.start', $first));

    // Now acting as $first (non super admin) — a second attempt must fail.
    $this->post(route('impersonation.start', $second))->assertForbidden();

    expect(ImpersonationLog::count())->toBe(1);
});

it('requires authentication to start or leave impersonation', function () {
    $tenant = Tenant::factory()->create();
    $target = tenantUserIn($tenant);

    $this->post(route('impersonation.start', $target))->assertRedirect();
    $this->post(route('impersonation.leave'))->assertRedirect();
    expect(ImpersonationLog::count())->toBe(0);
});

// ── Banner (UX) ───────────────────────────────────────────────────────────────

it('renders the permanent banner while impersonating', function () {
    session()->put(ImpersonationService::SESSION_KEY, [
        'log_id' => 'x',
        'impersonator_id' => 'y',
        'impersonated_user_id' => 'z',
        'impersonated_name' => 'Juan Pérez',
        'tenant_id' => 't',
        'tenant_name' => 'Empresa XYZ',
        'started_at' => now()->toIso8601String(),
    ]);

    $html = view('filament.impersonation-banner')->render();

    expect($html)->toContain('Estás actuando como Juan Pérez')
        ->toContain('Empresa XYZ')
        ->toContain('Salir de la impersonación')
        ->toContain(route('impersonation.leave'));
});

it('does not render the banner when not impersonating', function () {
    $html = view('filament.impersonation-banner')->render();

    expect($html)->not->toContain('Estás actuando como');
});
