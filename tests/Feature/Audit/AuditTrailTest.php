<?php

use App\Infrastructure\Audit\Jobs\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * PR-3 — Audit Trail: persistence, masking, and tenant isolation.
 */

// ── WriteAuditLog handle ──────────────────────────────────────────────────────

it('writes an audit record to the database', function () {
    $job = new WriteAuditLog(
        modelClass: User::class,
        modelKey: 'abc-123',
        event: 'created',
        oldValues: null,
        newValues: ['name' => 'Alex', 'email' => 'alex@example.com'],
        userId: null,
        tenantId: null,
        ipAddress: '127.0.0.1',
        userAgent: 'PHPUnit',
    );

    $job->handle();

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => User::class,
        'auditable_id' => 'abc-123',
        'event' => 'created',
        'old_values' => null,
        'ip_address' => '127.0.0.1',
    ]);
});

it('stores old_values on update', function () {
    $job = new WriteAuditLog(
        modelClass: User::class,
        modelKey: 'abc-456',
        event: 'updated',
        oldValues: ['name' => 'Before'],
        newValues: ['name' => 'After'],
        userId: null,
        tenantId: null,
        ipAddress: null,
        userAgent: null,
    );

    $job->handle();

    $record = AuditLog::where('auditable_id', 'abc-456')->sole();
    expect($record->old_values)->toBe(['name' => 'Before'])
        ->and($record->new_values)->toBe(['name' => 'After']);
});

it('stores null new_values on delete', function () {
    $job = new WriteAuditLog(
        modelClass: User::class,
        modelKey: 'abc-789',
        event: 'deleted',
        oldValues: ['name' => 'Alex'],
        newValues: null,
        userId: null,
        tenantId: null,
        ipAddress: null,
        userAgent: null,
    );

    $job->handle();

    $record = AuditLog::where('auditable_id', 'abc-789')->sole();
    expect($record->event)->toBe('deleted')
        ->and($record->new_values)->toBeNull();
});

it('records restore event', function () {
    $job = new WriteAuditLog(
        modelClass: User::class,
        modelKey: 'abc-restore',
        event: 'restored',
        oldValues: ['deleted_at' => '2026-01-01 00:00:00'],
        newValues: ['deleted_at' => null],
        userId: null,
        tenantId: null,
        ipAddress: null,
        userAgent: null,
    );

    $job->handle();

    $this->assertDatabaseHas('audit_logs', [
        'auditable_id' => 'abc-restore',
        'event' => 'restored',
    ]);
});

// ── Sensitive field masking ───────────────────────────────────────────────────

it('masks password fields before persisting', function () {
    $job = new WriteAuditLog(
        modelClass: User::class,
        modelKey: 'mask-test',
        event: 'updated',
        oldValues: ['name' => 'Alex', 'password' => 'old-hash'],
        newValues: ['name' => 'Alex', 'password' => 'new-hash', 'remember_token' => 'tok123'],
        userId: null,
        tenantId: null,
        ipAddress: null,
        userAgent: null,
    );

    $job->handle();

    $record = AuditLog::where('auditable_id', 'mask-test')->sole();

    expect($record->old_values['password'])->toBe('***')
        ->and($record->new_values['password'])->toBe('***')
        ->and($record->new_values['remember_token'])->toBe('***')
        ->and($record->new_values['name'])->toBe('Alex');
});

it('masks token and secret fields', function () {
    $job = new WriteAuditLog(
        modelClass: User::class,
        modelKey: 'mask-token',
        event: 'created',
        oldValues: null,
        newValues: [
            'access_token' => 'super-secret-token',
            'api_key' => 'key-12345',
            'webhook_secret' => 'wh-secret',
            'name' => 'visible',
        ],
        userId: null,
        tenantId: null,
        ipAddress: null,
        userAgent: null,
    );

    $job->handle();

    $record = AuditLog::where('auditable_id', 'mask-token')->sole();

    expect($record->new_values['access_token'])->toBe('***')
        ->and($record->new_values['api_key'])->toBe('***')
        ->and($record->new_values['webhook_secret'])->toBe('***')
        ->and($record->new_values['name'])->toBe('visible');
});

// ── Tenant isolation ─────────────────────────────────────────────────────────

it('stores tenant_id and user_id on the audit record', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $job = new WriteAuditLog(
        modelClass: User::class,
        modelKey: 'tenant-test',
        event: 'created',
        oldValues: null,
        newValues: ['name' => 'Test'],
        userId: $user->getKey(),
        tenantId: $tenant->getKey(),
        ipAddress: null,
        userAgent: null,
    );

    $job->handle();

    $record = AuditLog::where('auditable_id', 'tenant-test')->sole();
    expect($record->tenant_id)->toBe($tenant->getKey())
        ->and($record->user_id)->toBe($user->getKey())
        ->and($record->user->name)->toBe($user->name)
        ->and($record->tenant->name)->toBe($tenant->name);
});

it('can filter audit logs by tenant_id', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    foreach (['A1', 'A2'] as $key) {
        (new WriteAuditLog(User::class, $key, 'created', null, ['x' => 1], null, $tenantA->getKey(), null, null))->handle();
    }

    (new WriteAuditLog(User::class, 'B1', 'created', null, ['x' => 1], null, $tenantB->getKey(), null, null))->handle();

    expect(AuditLog::where('tenant_id', $tenantA->getKey())->count())->toBe(2)
        ->and(AuditLog::where('tenant_id', $tenantB->getKey())->count())->toBe(1);
});

// ── Model dispatch via Auditable trait ───────────────────────────────────────

it('dispatches WriteAuditLog when an auditable model is created', function () {
    Queue::fake();

    Tenant::factory()->create();

    app()->terminate();

    Queue::assertPushed(WriteAuditLog::class, fn ($job) => $job->event === 'created');
});

it('dispatches WriteAuditLog when an auditable model is updated', function () {
    $tenant = Tenant::factory()->create();

    Queue::fake();

    $tenant->update(['name' => 'Updated Name']);

    app()->terminate();

    Queue::assertPushed(WriteAuditLog::class, fn ($job): bool => $job->event === 'updated' && $job->modelClass === Tenant::class
    );
});

it('dispatches WriteAuditLog when an auditable model is soft-deleted', function () {
    $tenant = Tenant::factory()->create();

    Queue::fake();

    $tenant->delete();

    app()->terminate();

    Queue::assertPushed(WriteAuditLog::class, fn ($job): bool => $job->event === 'deleted');
});

it('dispatches WriteAuditLog when an auditable model is restored', function () {
    $tenant = Tenant::factory()->create();
    $tenant->delete();

    Queue::fake();

    $tenant->restore();

    app()->terminate();

    Queue::assertPushed(WriteAuditLog::class, fn ($job): bool => $job->event === 'restored');
});

// ── AuditLog model helpers ───────────────────────────────────────────────────

it('returns the short class name for auditable_type', function () {
    $log = new AuditLog(['auditable_type' => 'App\\Models\\WorkOrder']);

    expect($log->modelShortName())->toBe('WorkOrder');
});

it('returns localized event labels and correct badge colors', function () {
    $cases = [
        ['event' => 'created', 'label' => 'Creado', 'color' => 'success'],
        ['event' => 'updated', 'label' => 'Actualizado', 'color' => 'info'],
        ['event' => 'deleted', 'label' => 'Eliminado', 'color' => 'danger'],
        ['event' => 'restored', 'label' => 'Restaurado', 'color' => 'warning'],
    ];

    foreach ($cases as $case) {
        $log = new AuditLog(['event' => $case['event']]);
        expect($log->eventLabel())->toBe($case['label'])
            ->and($log->eventColor())->toBe($case['color']);
    }
});
