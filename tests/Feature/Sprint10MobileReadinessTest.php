<?php

use App\Models\Equipment;
use App\Models\EquipmentQrCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTechnician;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ── Helpers ───────────────────────────────────────────────────────────────────

function mobileSetup(array $abilities = ['work-orders.read', 'work-orders.write', 'equipment.read']): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('mobile-token', $abilities);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return [
        'tenant' => $tenant,
        'user' => $user,
        'token' => $tokenResult->plainTextToken,
    ];
}

function mobileHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── GET /api/v1/work-orders/mine ─────────────────────────────────────────────

it('GET work-orders/mine returns only WOs where the user is assigned as technician', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = mobileSetup(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $myWo = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);
    WorkOrderTechnician::factory()->create([
        'tenant_id' => $tenant->id,
        'work_order_id' => $myWo->id,
        'user_id' => $user->id,
    ]);

    // WO where user is NOT assigned
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(mobileHeaders($token))->getJson('/api/v1/work-orders/mine');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($myWo->id);
});

it('GET work-orders/mine returns empty list when user has no assignments', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(mobileHeaders($token))->getJson('/api/v1/work-orders/mine');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});

it('GET work-orders/mine requires work-orders.read ability', function () {
    ['token' => $token] = mobileSetup(['inventory.read']);

    $this->withHeaders(mobileHeaders($token))->getJson('/api/v1/work-orders/mine')
        ->assertForbidden();
});

// ── GET /api/v1/equipment/by-qr/{qr_token} ───────────────────────────────────

it('GET equipment/by-qr returns the equipment and increments scan_count', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['equipment.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $qrCode = EquipmentQrCode::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'is_active' => true,
        'scan_count' => 0,
    ]);

    $response = $this->withHeaders(mobileHeaders($token))
        ->getJson("/api/v1/equipment/by-qr/{$qrCode->qr_token}");

    $response->assertOk();
    expect($response->json('data.id'))->toBe($equipment->id);

    $this->assertDatabaseHas('equipment_qr_codes', [
        'id' => $qrCode->id,
        'scan_count' => 1,
    ]);
});

it('GET equipment/by-qr returns 404 for inactive QR code', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['equipment.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $qrCode = EquipmentQrCode::factory()->inactive()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $this->withHeaders(mobileHeaders($token))
        ->getJson("/api/v1/equipment/by-qr/{$qrCode->qr_token}")
        ->assertNotFound();
});

it('GET equipment/by-qr returns 404 for unknown token', function () {
    ['token' => $token] = mobileSetup(['equipment.read']);

    $this->withHeaders(mobileHeaders($token))
        ->getJson('/api/v1/equipment/by-qr/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

// ── POST /api/v1/work-orders/{id}/time-entries ───────────────────────────────

it('POST work-orders/time-entries creates a time log and recalculates hours', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/time-entries",
        [
            'started_at' => '2026-06-09T08:00:00Z',
            'ended_at' => '2026-06-09T10:30:00Z',
            'description' => 'Revisión de bomba hidráulica',
        ]
    );

    $response->assertCreated();
    expect($response->json('data.hours'))->toBe(2.5)
        ->and($response->json('data.work_order_id'))->toBe($workOrder->id);

    $this->assertDatabaseHas('work_order_time_logs', [
        'work_order_id' => $workOrder->id,
        'user_id' => $user->id,
    ]);
});

it('POST work-orders/time-entries accepts open entry without ended_at', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/time-entries",
        ['started_at' => '2026-06-09T08:00:00Z']
    );

    $response->assertCreated();
    expect($response->json('data.ended_at'))->toBeNull()
        ->and($response->json('data.hours'))->toBeNull();
});

it('POST work-orders/time-entries validates ended_at must be after started_at', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/time-entries",
        [
            'started_at' => '2026-06-09T10:00:00Z',
            'ended_at' => '2026-06-09T08:00:00Z',
        ]
    )->assertUnprocessable();
});

// ── POST /api/v1/work-orders/{id}/comments ───────────────────────────────────

it('POST work-orders/comments creates a comment', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/comments",
        ['body' => 'Se inspeccionó la bomba hidráulica.']
    );

    $response->assertCreated();
    expect($response->json('data.body'))->toBe('Se inspeccionó la bomba hidráulica.')
        ->and($response->json('data.is_internal'))->toBeFalse();

    $this->assertDatabaseHas('work_order_comments', [
        'work_order_id' => $workOrder->id,
        'user_id' => $user->id,
        'is_internal' => false,
    ]);
});

it('POST work-orders/comments respects is_internal flag', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/comments",
        ['body' => 'Nota interna.', 'is_internal' => true]
    );

    $response->assertCreated();
    expect($response->json('data.is_internal'))->toBeTrue();
});

it('POST work-orders/comments requires body', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/comments",
        []
    )->assertUnprocessable();
});

// ── POST /api/v1/work-orders/{id}/media ──────────────────────────────────────

it('POST work-orders/media uploads a file and creates an attachment record', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $file = UploadedFile::fake()->image('before.jpg', 800, 600);

    $response = $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/media",
        [
            'file' => $file,
            'attachment_type' => 'before_photo',
            'caption' => 'Estado inicial del equipo',
        ]
    );

    $response->assertCreated();
    expect($response->json('data.attachment_type'))->toBe('before_photo')
        ->and($response->json('data.file_name'))->toBe('before.jpg')
        ->and($response->json('data.caption'))->toBe('Estado inicial del equipo');

    $this->assertDatabaseHas('work_order_attachments', [
        'work_order_id' => $workOrder->id,
        'attachment_type' => 'before_photo',
        'uploaded_by' => $user->id,
    ]);
});

it('POST work-orders/media rejects invalid attachment_type', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $file = UploadedFile::fake()->image('photo.jpg');

    $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/media",
        ['file' => $file, 'attachment_type' => 'invalid_type']
    )->assertUnprocessable();
});

// ── POST /api/v1/work-orders/{id}/signature ──────────────────────────────────

it('POST work-orders/signature records a technician completion signature', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/signature",
        [
            'signature_type' => 'technician_completion',
            'notes' => 'Trabajo completado satisfactoriamente.',
        ]
    );

    $response->assertCreated();
    expect($response->json('data.signature_type'))->toBe('technician_completion')
        ->and($response->json('data.user_id'))->toBe($user->id);

    $this->assertDatabaseHas('work_order_signatures', [
        'work_order_id' => $workOrder->id,
        'user_id' => $user->id,
        'signature_type' => 'technician_completion',
    ]);
});

it('POST work-orders/signature is idempotent — second call updates the existing record', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/signature",
        ['signature_type' => 'technician_completion', 'notes' => 'Primera firma']
    )->assertCreated();

    $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/signature",
        ['signature_type' => 'technician_completion', 'notes' => 'Firma corregida']
    )->assertCreated();

    $this->assertDatabaseCount('work_order_signatures', 1);
});

it('POST work-orders/signature rejects invalid signature_type', function () {
    ['tenant' => $tenant, 'token' => $token] = mobileSetup(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $this->withHeaders(mobileHeaders($token))->postJson(
        "/api/v1/work-orders/{$workOrder->id}/signature",
        ['signature_type' => 'invalid_type']
    )->assertUnprocessable();
});
