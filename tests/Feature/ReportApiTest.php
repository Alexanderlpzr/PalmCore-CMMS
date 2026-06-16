<?php

use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;

function reportApiUser(array $abilities): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('reports', $abilities);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['tenant' => $tenant, 'token' => $tokenResult->plainTextToken];
}

function reportApiHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/pdf'];
}

beforeEach(function () {
    Pdf::shouldReceive('loadView')->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('setOption')->andReturnSelf();
    Pdf::shouldReceive('output')->andReturn('%PDF-1.4 test');
});

it('GET /api/v1/reports/reliability streams a branded PDF', function () {
    ['token' => $token] = reportApiUser(['reports.read']);

    $response = $this->withHeaders(reportApiHeaders($token))->get('/api/v1/reports/reliability');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('.pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

it('GET /api/v1/reports/reliability is forbidden without reports.read', function () {
    ['token' => $token] = reportApiUser(['equipment.read']);

    $this->withHeaders(reportApiHeaders($token))
        ->get('/api/v1/reports/reliability')
        ->assertForbidden();
});

it('GET /api/v1/reports/work-orders/{id} streams a PDF for a tenant work order', function () {
    ['tenant' => $tenant, 'token' => $token] = reportApiUser(['reports.read']);
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeaders(reportApiHeaders($token))
        ->get('/api/v1/reports/work-orders/'.$wo->id)
        ->assertOk();
});

it('GET /api/v1/reports/work-orders/{id} returns 404 for another tenant work order', function () {
    ['token' => $token] = reportApiUser(['reports.read']);
    $otherTenant = Tenant::factory()->create();
    $otherWo = WorkOrder::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->withHeaders(reportApiHeaders($token))
        ->get('/api/v1/reports/work-orders/'.$otherWo->id)
        ->assertNotFound();
});

it('GET /api/v1/reports/equipment/{id} streams a PDF for tenant equipment', function () {
    ['tenant' => $tenant, 'token' => $token] = reportApiUser(['reports.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeaders(reportApiHeaders($token))
        ->get('/api/v1/reports/equipment/'.$equipment->id)
        ->assertOk();
});

it('report endpoints require authentication', function () {
    $this->get('/api/v1/reports/reliability')->assertUnauthorized();
});
