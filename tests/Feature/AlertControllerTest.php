<?php

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Models\Alert;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;

// ── Helpers ───────────────────────────────────────────────────────────────────

function alertCtx(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('test-token', ['*']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return [
        'tenant' => $tenant,
        'user' => $user,
        'token' => $tokenResult->plainTextToken,
    ];
}

function alertCtxHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

function openAlert(string $tenantId, AlertSeverity $severity = AlertSeverity::Warning): Alert
{
    return Alert::forceCreate([
        'tenant_id' => $tenantId,
        'severity' => $severity->value,
        'category' => AlertCategory::Maintenance->value,
        'title' => 'Test alert',
        'status' => 'open',
    ]);
}

// ── PATCH /api/v1/alerts/{alert}/resolve ─────────────────────────────────────

it('PATCH resolve devuelve status=resolved para alerta abierta', function () {
    Event::fake();

    ['tenant' => $tenant, 'token' => $token] = alertCtx();
    $alert = openAlert($tenant->id);

    $response = $this->withHeaders(alertCtxHeaders($token))
        ->patchJson("/api/v1/alerts/{$alert->id}/resolve");

    $response->assertOk()
        ->assertJson(['status' => 'resolved']);

    expect(Alert::withoutGlobalScopes()->find($alert->id)->status->value)->toBe('resolved');
});

it('PATCH resolve devuelve status=already_closed si la alerta ya estaba resuelta', function () {
    Event::fake();

    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = alertCtx();
    $alert = openAlert($tenant->id);

    app(AlertService::class)->resolve($alert, $user);

    $response = $this->withHeaders(alertCtxHeaders($token))
        ->patchJson("/api/v1/alerts/{$alert->id}/resolve");

    $response->assertOk()
        ->assertJson(['status' => 'already_closed']);
});

it('PATCH resolve devuelve status=already_closed si la alerta fue descartada', function () {
    Event::fake();

    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = alertCtx();
    $alert = openAlert($tenant->id, AlertSeverity::Warning);

    app(AlertService::class)->dismiss($alert, $user);

    $response = $this->withHeaders(alertCtxHeaders($token))
        ->patchJson("/api/v1/alerts/{$alert->id}/resolve");

    $response->assertOk()
        ->assertJson(['status' => 'already_closed']);
});

it('PATCH resolve devuelve 401 sin autenticación', function () {
    $tenant = Tenant::factory()->create();
    $alert = openAlert($tenant->id);

    $this->patchJson("/api/v1/alerts/{$alert->id}/resolve")
        ->assertUnauthorized();
});

it('PATCH resolve devuelve 404 para alerta de otro tenant', function () {
    ['token' => $token] = alertCtx();

    $otherTenant = Tenant::factory()->create();
    $alertOtherTenant = openAlert($otherTenant->id);

    $this->withHeaders(alertCtxHeaders($token))
        ->patchJson("/api/v1/alerts/{$alertOtherTenant->id}/resolve")
        ->assertNotFound();
});

// ── PATCH /api/v1/alerts/{alert}/dismiss ─────────────────────────────────────

it('PATCH dismiss devuelve status=dismissed para alerta no crítica', function () {
    Event::fake();

    ['tenant' => $tenant, 'token' => $token] = alertCtx();
    $alert = openAlert($tenant->id, AlertSeverity::Warning);

    $this->withHeaders(alertCtxHeaders($token))
        ->patchJson("/api/v1/alerts/{$alert->id}/dismiss")
        ->assertOk()
        ->assertJson(['status' => 'dismissed']);
});

it('PATCH dismiss rechaza descartar una alerta crítica con 422', function () {
    Event::fake();

    ['tenant' => $tenant, 'token' => $token] = alertCtx();
    $alert = openAlert($tenant->id, AlertSeverity::Critical);

    $this->withHeaders(alertCtxHeaders($token))
        ->patchJson("/api/v1/alerts/{$alert->id}/dismiss")
        ->assertStatus(422)
        ->assertJson(['message' => 'cannot_dismiss_critical']);

    expect(Alert::withoutGlobalScopes()->find($alert->id)->status->value)->toBe('open');
});

it('PATCH dismiss devuelve status=already_closed si la alerta ya estaba cerrada', function () {
    Event::fake();

    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = alertCtx();
    $alert = openAlert($tenant->id, AlertSeverity::Warning);

    app(AlertService::class)->resolve($alert, $user);

    $this->withHeaders(alertCtxHeaders($token))
        ->patchJson("/api/v1/alerts/{$alert->id}/dismiss")
        ->assertOk()
        ->assertJson(['status' => 'already_closed']);
});

it('PATCH dismiss devuelve 404 para alerta de otro tenant', function () {
    ['token' => $token] = alertCtx();

    $otherTenant = Tenant::factory()->create();
    $alertOtherTenant = openAlert($otherTenant->id, AlertSeverity::Warning);

    $this->withHeaders(alertCtxHeaders($token))
        ->patchJson("/api/v1/alerts/{$alertOtherTenant->id}/dismiss")
        ->assertNotFound();
});
