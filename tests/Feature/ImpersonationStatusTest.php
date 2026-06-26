<?php

use App\Services\ImpersonationService;

/**
 * PR-5 — Impersonation status endpoint for SPA.
 */
it('returns active false when no impersonation session is set', function () {
    $response = $this->getJson('/api/v1/impersonation/status');

    $response->assertOk()
        ->assertJson(['active' => false, 'context' => null]);
});

it('returns active true with context when impersonation session is set', function () {
    $context = [
        'log_id' => 'some-uuid',
        'impersonator_id' => 'admin-uuid',
        'impersonated_user_id' => 'alice-uuid',
        'impersonated_name' => 'Alice',
        'tenant_id' => 'tenant-uuid',
        'tenant_name' => 'Acme Corp',
        'started_at' => now()->toIso8601String(),
    ];

    session()->put(ImpersonationService::SESSION_KEY, $context);

    $response = $this->getJson('/api/v1/impersonation/status');

    $response->assertOk()
        ->assertJson([
            'active' => true,
            'context' => [
                'impersonated_name' => 'Alice',
                'tenant_name' => 'Acme Corp',
            ],
        ]);
});

it('health endpoint returns healthy status with version field', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJsonStructure(['status', 'timestamp', 'version', 'checks']);

    expect($response->json('status'))->toBeIn(['healthy', 'degraded']);
});

it('web health endpoint uses same status vocabulary as api health endpoint', function () {
    $webResponse = $this->get('/health');
    $apiResponse = $this->getJson('/api/health');

    // Both should use 'healthy' or 'degraded', not 'ok'
    $webStatus = $webResponse->json('status');
    $apiStatus = $apiResponse->json('status');

    expect($webStatus)->toBeIn(['healthy', 'degraded'])
        ->and($apiStatus)->toBeIn(['healthy', 'degraded']);
});
