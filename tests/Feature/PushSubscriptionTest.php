<?php

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Notifications\WorkOrderAssignedNotification;
use App\Domain\Notifications\WorkOrderStatusChangedNotification;
use App\Models\Equipment;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

// ── Helpers ───────────────────────────────────────────────────────────────────

function pushTenantWithUser(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('push-test', ['*']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['tenant' => $tenant, 'user' => $user, 'token' => $tokenResult->plainTextToken];
}

function pushHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── POST /push-subscriptions ──────────────────────────────────────────────────

it('stores a new push subscription', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = pushTenantWithUser();

    $response = $this->postJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
        'public_key' => base64_encode('fake-p256dh-key'),
        'auth_token' => base64_encode('fake-auth-key'),
        'content_encoding' => 'aes128gcm',
        'device_name' => 'Chrome Android',
    ], pushHeaders($token));

    $response->assertNoContent();

    $this->assertDatabaseHas('push_subscriptions', [
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'content_encoding' => 'aes128gcm',
        'device_name' => 'Chrome Android',
        'is_active' => true,
    ]);
});

it('upserts an existing subscription instead of creating a duplicate', function () {
    ['user' => $user, 'token' => $token] = pushTenantWithUser();

    $payload = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/stable-endpoint',
        'public_key' => base64_encode('key-v1'),
        'auth_token' => base64_encode('auth-v1'),
    ];

    $this->postJson('/api/v1/push-subscriptions', $payload, pushHeaders($token));
    $this->postJson('/api/v1/push-subscriptions', array_merge($payload, [
        'public_key' => base64_encode('key-v2'),
        'auth_token' => base64_encode('auth-v2'),
    ]), pushHeaders($token));

    expect(PushSubscription::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
});

it('requires authenticated user to store subscription', function () {
    $this->postJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://example.com/push',
        'public_key' => 'key',
        'auth_token' => 'auth',
    ])->assertUnauthorized();
});

it('validates endpoint is a URL', function () {
    ['token' => $token] = pushTenantWithUser();

    $this->postJson('/api/v1/push-subscriptions', [
        'endpoint' => 'not-a-url',
        'public_key' => 'key',
        'auth_token' => 'auth',
    ], pushHeaders($token))->assertUnprocessable();
});

// ── DELETE /push-subscriptions ────────────────────────────────────────────────

it('deactivates a push subscription on delete', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = pushTenantWithUser();

    $endpoint = 'https://fcm.googleapis.com/fcm/send/deletable-endpoint';

    PushSubscription::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'endpoint' => $endpoint,
        'public_key' => 'key',
        'auth_token' => 'auth',
        'is_active' => true,
    ]);

    $this->deleteJson('/api/v1/push-subscriptions?endpoint='.urlencode($endpoint), [], pushHeaders($token))
        ->assertNoContent();

    $this->assertDatabaseHas('push_subscriptions', [
        'user_id' => $user->id,
        'endpoint' => $endpoint,
        'is_active' => false,
    ]);
});

// ── Notification dispatch ─────────────────────────────────────────────────────

it('dispatches WorkOrderAssignedNotification when a technician is newly assigned', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $creator = User::factory()->create();
    $technician = User::factory()->create();

    $service = app(WorkOrderService::class);

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test WO',
        'description' => 'desc',
    ], $creator);

    $service->assignTechnician($wo, $technician, TechnicianRole::Technician->value);

    Notification::assertSentTo($technician, WorkOrderAssignedNotification::class);
});

it('does not dispatch WorkOrderAssignedNotification when assigning a second (different) technician is unrelated to the first', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $creator = User::factory()->create();
    $technician1 = User::factory()->create();
    $technician2 = User::factory()->create();

    $service = app(WorkOrderService::class);

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test WO',
        'description' => 'desc',
    ], $creator);

    $service->assignTechnician($wo, $technician1, TechnicianRole::Technician->value);
    $service->assignTechnician($wo, $technician2, TechnicianRole::Lead->value);

    // Each newly assigned technician gets their own notification.
    Notification::assertSentTo($technician1, WorkOrderAssignedNotification::class);
    Notification::assertSentTo($technician2, WorkOrderAssignedNotification::class);
    Notification::assertCount(2);
});

it('dispatches WorkOrderStatusChangedNotification when WO transitions to InProgress', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $creator = User::factory()->create();
    $technician = User::factory()->create();

    $service = app(WorkOrderService::class);

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test WO',
        'description' => 'desc',
    ], $creator);

    // Attach the technician directly to have someone to notify.
    $wo->technicians()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $technician->id,
        'role' => TechnicianRole::Technician->value,
    ]);

    $service->transition($wo, WorkOrderStatus::Planned, $creator);
    $service->transition($wo, WorkOrderStatus::InProgress, $creator);

    Notification::assertSentTo($technician, WorkOrderStatusChangedNotification::class);
});
