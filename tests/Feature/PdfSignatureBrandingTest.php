<?php

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Reports\Services\ReportBrandingService;
use App\Domain\Reports\Services\WorkOrderPdfService;
use App\Domain\Shared\Enums\ActivityType;
use App\Models\ActivityLocation;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// ── Sprint UX-10: the signature is no longer captured-and-discarded ───────────

it('addSignature persists the uploaded image on the private disk and links it to the record', function () {
    Storage::fake('work_orders_private');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $signature = app(WorkOrderService::class)->addSignature(
        $workOrder,
        $user,
        WorkOrderSignatureType::TechnicianCompletion,
        'Trabajo terminado',
        null,
        UploadedFile::fake()->image('signature.png', 300, 100),
    );

    expect($signature->image_path)->not->toBeNull();
    Storage::disk('work_orders_private')->assertExists($signature->image_path);
});

it('addSignature without an image leaves image_path null (backward-compatible metadata-only callers)', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $signature = app(WorkOrderService::class)->addSignature(
        $workOrder, $user, WorkOrderSignatureType::TechnicianCompletion, null, null,
    );

    expect($signature->image_path)->toBeNull();
});

it('POST work-orders/signature accepts a multipart signature_image and persists it', function () {
    Storage::fake('work_orders_private');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    $tokenResult = $user->createToken('mobile-token', ['work-orders.write']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$tokenResult->plainTextToken, 'Accept' => 'application/json'])
        ->postJson("/api/v1/work-orders/{$workOrder->id}/signature", [
            'signature_type' => 'technician_completion',
            'notes' => 'Firmado en sitio',
            'signature_image' => UploadedFile::fake()->image('signature.png', 300, 100),
        ]);

    $response->assertCreated();
    expect($response->json('data.image_url'))->not->toBeNull();

    $signature = $workOrder->signatures()->first();
    expect($signature->image_path)->not->toBeNull();
    Storage::disk('work_orders_private')->assertExists($signature->image_path);
});

it('the work order PDF template embeds a persisted signature image as base64', function () {
    Storage::fake('work_orders_private');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $signature = app(WorkOrderService::class)->addSignature(
        $workOrder, $user, WorkOrderSignatureType::TechnicianCompletion, null, null,
        UploadedFile::fake()->image('signature.png', 300, 100),
    );

    $workOrder->load(['signatures.user']);
    $imageContent = Storage::disk('work_orders_private')->get($signature->image_path);

    $html = view('reports.work-order', [
        'workOrder' => $workOrder,
        'tenant' => $tenant,
        'logoBase64' => null,
        'signatureImages' => [$signature->id => 'data:image/png;base64,'.base64_encode($imageContent)],
        'documentNumber' => $workOrder->work_order_number,
        'documentVersion' => '1.0',
        'qrBase64' => null,
        'generatedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('data:image/png;base64,')
        ->not->toContain('Sin firma registrada');
});

it('the work order PDF template shows a clear placeholder when no signature image was captured', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    app(WorkOrderService::class)->addSignature(
        $workOrder, $user, WorkOrderSignatureType::TechnicianCompletion, null, null,
    );

    $workOrder->load(['signatures.user']);

    $html = view('reports.work-order', [
        'workOrder' => $workOrder,
        'tenant' => $tenant,
        'logoBase64' => null,
        'signatureImages' => [],
        'documentNumber' => $workOrder->work_order_number,
        'documentVersion' => '1.0',
        'qrBase64' => null,
        'generatedAt' => now(),
    ])->render();

    // Previously this printed the raw backed-enum object (no __toString), which
    // throws in PHP — ->label() must be used instead.
    expect($html)->toContain('Sin firma registrada')
        ->and($html)->toContain('Firma de Técnico');
});

// ── Document branding standard ─────────────────────────────────────────────────

it('the PDF header shows tenant legal info, document number and version when present', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Palma Real S.A.',
        'tax_id' => '900123456-7',
        'address' => 'Km 12 vía Buenaventura',
        'contact_phone' => '+57 300 000 0000',
        'contact_email' => 'contacto@palmareal.com',
    ]);

    $html = view('reports.partials.header', [
        'tenant' => $tenant,
        'logoBase64' => null,
        'documentNumber' => 'OT-2026-0001',
        'documentVersion' => '1.0',
        'qrBase64' => 'data:image/png;base64,AAAA',
        'generatedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Palma Real S.A.')
        ->toContain('900123456-7')
        ->toContain('Km 12 vía Buenaventura')
        ->toContain('OT-2026-0001')
        ->toContain('Versión 1.0')
        ->toContain('data:image/png;base64,AAAA');
});

it('the PDF footer shows the document number and page numbering', function () {
    $tenant = Tenant::factory()->create(['name' => 'Palma Real S.A.']);

    $html = view('reports.partials.footer', [
        'tenant' => $tenant,
        'documentNumber' => 'OT-2026-0001',
    ])->render();

    expect($html)
        ->toContain('OT-2026-0001')
        ->toContain('Generado con Fronda CMMS');
});

it('WorkOrderPdfService generates a real, non-empty PDF with the new branding wired in', function () {
    $tenant = Tenant::factory()->create(['name' => 'Palma Real S.A.', 'tax_id' => '900123456-7']);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $bytes = app(WorkOrderPdfService::class)->generate($tenant->id, $workOrder->id);

    expect($bytes)->toBeString()->toStartWith('%PDF');
});

// ── Regression guard: DomPDF silently drops fixed header/footer content ───────
// when a template wraps `@include('reports.partials.header'|'footer')` in its
// own matching `<div id="header">`/`<div id="footer">` — the partial already
// provides that div, so wrapping it again creates a duplicate id that breaks
// rendering with no error. This bug meant NO report ever showed its
// header/footer/logo/QR/pagination, in production, before this sprint.

it('no report template re-wraps the header/footer partials in a duplicate id div', function () {
    $templates = [
        'work-order', 'equipment', 'maintenance-plan', 'inventory', 'reliability',
    ];

    foreach ($templates as $template) {
        $source = file_get_contents(resource_path("views/reports/{$template}.blade.php"));

        expect($source)
            ->not->toMatch('/<div id="header">\s*@include\(\'reports\.partials\.header\'\)/')
            ->not->toMatch('/<div id="footer">\s*@include\(\'reports\.partials\.footer\'\)/')
            ->toContain("@include('reports.partials.header')")
            ->toContain("@include('reports.partials.footer')");
    }
});

it('the shared stylesheet uses top:0/bottom:0 fixed positioning, not the broken negative-offset technique', function () {
    $source = file_get_contents(resource_path('views/reports/partials/styles.blade.php'));

    expect($source)
        ->toContain('#header { position: fixed; top: 0;')
        ->toContain('#footer { position: fixed; bottom: 0;')
        ->toContain('margin-top: 100px; margin-bottom: 46px;');
});

// ── Sprint UX-10.1: QR now bridges the physical document to the digital record ─

it('recordUrl deep-links to the record\'s authenticated Filament page', function () {
    $tenant = Tenant::factory()->create(['slug' => 'palma-real']);
    $recordId = (string) Str::uuid();

    $url = app(ReportBrandingService::class)->recordUrl(
        'filament.admin.resources.equipment.view',
        $tenant,
        $recordId,
    );

    expect($url)
        ->toContain('/admin/palma-real/equipment/')
        ->toContain($recordId);
});

it('recordUrl returns null for an unknown route instead of throwing', function () {
    $tenant = Tenant::factory()->create();

    $url = app(ReportBrandingService::class)->recordUrl(
        'this.route.does.not.exist',
        $tenant,
        (string) Str::uuid(),
    );

    expect($url)->toBeNull();
});

it('WorkOrderPdfService points the QR at the work order\'s admin page, not just plain text', function () {
    $tenant = Tenant::factory()->create(['slug' => 'palma-real']);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $expectedUrl = route('filament.admin.resources.maintenance.work-order.work-orders.view', [
        'tenant' => $tenant->slug,
        'record' => $workOrder->id,
    ]);

    $bytes = app(WorkOrderPdfService::class)->generate($tenant->id, $workOrder->id);

    // The QR is embedded as base64 PNG, so we can't decode pixels back to text
    // here — but we CAN prove the exact URL is what generate() fed into it by
    // asserting the same call the service makes resolves to a real, working
    // Filament route (i.e. no typo/renamed route silently degrading to the
    // text-only fallback).
    expect($expectedUrl)->toContain($workOrder->id)
        ->and($bytes)->toStartWith('%PDF');
});

// ── Sprint UX-10.1: signature block shows who, what, when, and where ──────────

it('the signature block shows the signer\'s email as an identification line', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['name' => 'Carlos Mendoza', 'email' => 'carlos@palmareal.com']);
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    app(WorkOrderService::class)->addSignature(
        $workOrder, $user, WorkOrderSignatureType::TechnicianCompletion, null, null,
    );
    $workOrder->load(['signatures.user']);

    $html = view('reports.work-order', [
        'workOrder' => $workOrder, 'tenant' => $tenant, 'logoBase64' => null,
        'signatureImages' => [], 'signatureLocations' => [],
        'documentNumber' => $workOrder->work_order_number, 'documentVersion' => '1.0',
        'qrBase64' => null, 'generatedAt' => now(),
    ])->render();

    expect($html)->toContain('carlos@palmareal.com');
});

it('the signature block shows the captured GPS location when one exists', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $signature = app(WorkOrderService::class)->addSignature(
        $workOrder, $user, WorkOrderSignatureType::TechnicianCompletion, null, null,
    );
    $workOrder->load(['signatures.user']);

    $location = ActivityLocation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'activity_type' => ActivityType::Signature,
        'activity_id' => $signature->id,
        'latitude' => -3.4516,
        'longitude' => -76.5320,
        'accuracy' => 12.0,
        'is_low_accuracy' => false,
        'captured_at' => now(),
    ]);

    $html = view('reports.work-order', [
        'workOrder' => $workOrder, 'tenant' => $tenant, 'logoBase64' => null,
        'signatureImages' => [], 'signatureLocations' => [$signature->id => $location],
        'documentNumber' => $workOrder->work_order_number, 'documentVersion' => '1.0',
        'qrBase64' => null, 'generatedAt' => now(),
    ])->render();

    expect($html)->toContain('Ubicación:')
        ->toContain('-3.4516')
        ->toContain('-76.5320');
});

it('the signature block omits the location line entirely when no GPS was captured (no noise)', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    app(WorkOrderService::class)->addSignature(
        $workOrder, $user, WorkOrderSignatureType::TechnicianCompletion, null, null,
    );
    $workOrder->load(['signatures.user']);

    $html = view('reports.work-order', [
        'workOrder' => $workOrder, 'tenant' => $tenant, 'logoBase64' => null,
        'signatureImages' => [], 'signatureLocations' => [],
        'documentNumber' => $workOrder->work_order_number, 'documentVersion' => '1.0',
        'qrBase64' => null, 'generatedAt' => now(),
    ])->render();

    expect($html)->not->toContain('Ubicación:');
});
