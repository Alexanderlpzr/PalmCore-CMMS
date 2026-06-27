<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderSignature;
use App\Models\WorkOrderTimeLog;

// ── Helpers ───────────────────────────────────────────────────────────────────

function woTabsCtx(): array
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

function woTabsHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── Auth guard ──────────────────────────────────────────────────────────────

it('media index requiere autenticación', function () {
    ['tenant' => $tenant] = woTabsCtx();
    $wo = WorkOrder::factory()->for($tenant)->create();

    $this->getJson("/api/v1/work-orders/{$wo->id}/media")->assertUnauthorized();
});

// ── Media ─────────────────────────────────────────────────────────────────────

it('media index retorna adjuntos de la OT', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = woTabsCtx();
    $wo = WorkOrder::factory()->for($tenant)->create();

    $attachment = WorkOrderAttachment::factory()->beforePhoto()->create([
        'tenant_id' => $tenant->id,
        'work_order_id' => $wo->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->withHeaders(woTabsHeaders($token))
        ->getJson("/api/v1/work-orders/{$wo->id}/media");

    $response->assertOk()
        ->assertJsonPath('data.0.id', $attachment->id)
        ->assertJsonPath('data.0.attachment_type', 'before_photo');
});

// ── Signatures ──────────────────────────────────────────────────────────────

it('signatures index retorna firmas con usuario', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = woTabsCtx();
    $wo = WorkOrder::factory()->for($tenant)->create();

    $signature = WorkOrderSignature::factory()->create([
        'tenant_id' => $tenant->id,
        'work_order_id' => $wo->id,
        'user_id' => $user->id,
    ]);

    $response = $this->withHeaders(woTabsHeaders($token))
        ->getJson("/api/v1/work-orders/{$wo->id}/signatures");

    $response->assertOk()
        ->assertJsonPath('data.0.id', $signature->id)
        ->assertJsonPath('data.0.user.id', $user->id)
        ->assertJsonPath('data.0.user.name', $user->name);
});

// ── Time entries ────────────────────────────────────────────────────────────

it('time-entries index retorna registros de tiempo', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = woTabsCtx();
    $wo = WorkOrder::factory()->for($tenant)->create();

    $log = WorkOrderTimeLog::factory()->create([
        'tenant_id' => $tenant->id,
        'work_order_id' => $wo->id,
        'user_id' => $user->id,
        'hours' => 2.5,
    ]);

    $response = $this->withHeaders(woTabsHeaders($token))
        ->getJson("/api/v1/work-orders/{$wo->id}/time-entries");

    $response->assertOk()
        ->assertJsonPath('data.0.id', $log->id)
        ->assertJsonPath('data.0.hours', 2.5)
        ->assertJsonPath('data.0.user.name', $user->name);
});

// ── Tenant isolation ──────────────────────────────────────────────────────────

it('endpoints respetan aislamiento de tenant', function () {
    // Tenant A owns the work order + attachment.
    $tenantA = Tenant::factory()->create();
    $userA = User::factory()->create(['is_active' => true]);
    $userA->tenants()->attach($tenantA->id, ['joined_at' => now()]);
    $wo = WorkOrder::factory()->for($tenantA)->create();
    $attachment = WorkOrderAttachment::factory()->create([
        'tenant_id' => $tenantA->id,
        'work_order_id' => $wo->id,
        'uploaded_by' => $userA->id,
    ]);

    // Tenant B queries with its own token → must not see tenant A's WO.
    ['token' => $tokenB] = woTabsCtx();

    $response = $this->withHeaders(woTabsHeaders($tokenB))
        ->getJson("/api/v1/work-orders/{$wo->id}/media");

    $response->assertNotFound();
    expect($response->getContent())->not->toContain($attachment->id);
});
