<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Shared\Enums\ActivityType;
use App\Models\ActivityLocation;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

// ── Helpers ───────────────────────────────────────────────────────────────────

function geoTenantWithUser(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('geo-test', ['*']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['tenant' => $tenant, 'user' => $user, 'token' => $tokenResult->plainTextToken];
}

function geoHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

function validGps(array $overrides = []): array
{
    return array_merge([
        'latitude' => -34.6037,
        'longitude' => -58.3816,
        'accuracy' => 12.5,
        'source' => 'gps',
        'gps_timestamp' => now()->toISOString(),
    ], $overrides);
}

function createWorkOrder(Tenant $tenant, User $creator): WorkOrder
{
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    return app(WorkOrderService::class)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test WO',
        'description' => 'desc',
    ], $creator);
}

// ── Time entry GPS ────────────────────────────────────────────────────────────

it('records GPS location when logging time', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = geoTenantWithUser();

    $wo = createWorkOrder($tenant, $user);

    $this->postJson("/api/v1/work-orders/{$wo->id}/time-entries", [
        'started_at' => now()->subHour()->toISOString(),
        'ended_at' => now()->toISOString(),
        'gps' => validGps(),
    ], geoHeaders($token))->assertCreated();

    $this->assertDatabaseHas('activity_locations', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'activity_type' => ActivityType::TimeLog->value,
        'source' => 'gps',
        'is_low_accuracy' => false,
    ]);
});

it('does not create activity_location when GPS is absent from time entry', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = geoTenantWithUser();

    $wo = createWorkOrder($tenant, $user);

    $this->postJson("/api/v1/work-orders/{$wo->id}/time-entries", [
        'started_at' => now()->subHour()->toISOString(),
    ], geoHeaders($token))->assertCreated();

    expect(ActivityLocation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('records GPS location when posting a comment', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = geoTenantWithUser();

    $wo = createWorkOrder($tenant, $user);

    $this->postJson("/api/v1/work-orders/{$wo->id}/comments", [
        'body' => 'Revisado el panel',
        'gps' => validGps(['accuracy' => 8.0, 'source' => 'gps']),
    ], geoHeaders($token))->assertCreated();

    $this->assertDatabaseHas('activity_locations', [
        'tenant_id' => $tenant->id,
        'activity_type' => ActivityType::Comment->value,
        'is_low_accuracy' => false,
    ]);
});

it('records GPS location when uploading a photo', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = geoTenantWithUser();

    $wo = createWorkOrder($tenant, $user);

    $file = UploadedFile::fake()->image('foto.jpg', 100, 100);

    $this->post("/api/v1/work-orders/{$wo->id}/media", [
        'file' => $file,
        'attachment_type' => 'evidence',
        'gps' => validGps(),
    ], geoHeaders($token))->assertCreated();

    $this->assertDatabaseHas('activity_locations', [
        'tenant_id' => $tenant->id,
        'activity_type' => ActivityType::Photo->value,
    ]);
});

it('records GPS location when adding a signature', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = geoTenantWithUser();

    $wo = createWorkOrder($tenant, $user);

    $this->postJson("/api/v1/work-orders/{$wo->id}/signature", [
        'signature_type' => 'technician_completion',
        'gps' => validGps(),
    ], geoHeaders($token))->assertCreated();

    $this->assertDatabaseHas('activity_locations', [
        'tenant_id' => $tenant->id,
        'activity_type' => ActivityType::Signature->value,
    ]);
});

it('records GPS location on status transition', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = geoTenantWithUser();

    $wo = createWorkOrder($tenant, $user);

    $this->patchJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => WorkOrderStatus::Planned->value,
        'gps' => validGps(),
    ], geoHeaders($token))->assertOk();

    $this->assertDatabaseHas('activity_locations', [
        'tenant_id' => $tenant->id,
        'activity_type' => ActivityType::StatusChange->value,
        'activity_id' => $wo->id,
    ]);
});

it('flags is_low_accuracy when accuracy exceeds 100 metres', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = geoTenantWithUser();

    $wo = createWorkOrder($tenant, $user);

    $this->postJson("/api/v1/work-orders/{$wo->id}/time-entries", [
        'started_at' => now()->subHour()->toISOString(),
        'gps' => validGps(['accuracy' => 350.0, 'source' => 'network']),
    ], geoHeaders($token))->assertCreated();

    $this->assertDatabaseHas('activity_locations', [
        'tenant_id' => $tenant->id,
        'activity_type' => ActivityType::TimeLog->value,
        'is_low_accuracy' => true,
        'source' => 'network',
    ]);
});

it('rejects GPS payload with coordinates out of valid range', function () {
    ['token' => $token] = geoTenantWithUser();
    $wo = createWorkOrder(Tenant::factory()->create(), User::factory()->create());

    $this->postJson("/api/v1/work-orders/{$wo->id}/time-entries", [
        'started_at' => now()->toISOString(),
        'gps' => ['latitude' => 200.0, 'longitude' => 0.0, 'accuracy' => 10.0],
    ], geoHeaders($token))->assertUnprocessable();
});

it('preserves gps_timestamp as captured_at (device time, not server time)', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = geoTenantWithUser();

    $wo = createWorkOrder($tenant, $user);
    $deviceTime = now()->subMinutes(3)->toISOString();

    $this->postJson("/api/v1/work-orders/{$wo->id}/comments", [
        'body' => 'Revisión offline',
        'gps' => validGps(['gps_timestamp' => $deviceTime]),
    ], geoHeaders($token))->assertCreated();

    $location = ActivityLocation::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('activity_type', ActivityType::Comment->value)
        ->first();

    expect($location)->not->toBeNull();
    expect($location->captured_at->timestamp)->toBe(Carbon::parse($deviceTime)->timestamp);
});
