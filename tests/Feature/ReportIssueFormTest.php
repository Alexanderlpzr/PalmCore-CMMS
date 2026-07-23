<?php

use App\Livewire\Equipment\ReportForm;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\EquipmentQrCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

// ── Setup helper ──────────────────────────────────────────────────────────────

function makeQrWithEquipment(): array
{
    $equipment = Equipment::factory()->create();

    $qrCode = EquipmentQrCode::withoutGlobalScopes()->create([
        'equipment_id' => $equipment->id,
        'tenant_id' => $equipment->tenant_id,
        'qr_token' => (string) Str::uuid(),
        'qr_image_path' => null,
        'is_active' => true,
        'generated_at' => now(),
        'scan_count' => 0,
    ]);

    return [$equipment, $qrCode];
}

/** Un envío válido completo (nombre y cargo ahora son obligatorios). */
function fillValidReport($component): mixed
{
    return $component
        ->set('description', 'El motor presenta vibración excesiva en el eje principal.')
        ->set('severity', 'high')
        ->set('reporterName', 'Juan Pérez')
        ->set('reporterPosition', 'Operario de planta');
}

// ── Rendering ─────────────────────────────────────────────────────────────────

it('renders the report form component', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode])
        ->assertStatus(200)
        ->assertSee('Reportar novedad')
        ->assertSee('Cargo');
});

// ── Validación ────────────────────────────────────────────────────────────────

it('fails validation when description is empty', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode])
        ->set('description', '')
        ->call('submit')
        ->assertHasErrors(['description']);
});

it('requires the reporter name', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    fillValidReport(Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode]))
        ->set('reporterName', '')
        ->call('submit')
        ->assertHasErrors(['reporterName']);
});

it('requires the reporter position (cargo)', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    fillValidReport(Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode]))
        ->set('reporterPosition', '')
        ->call('submit')
        ->assertHasErrors(['reporterPosition']);
});

it('rejects a non-image photo', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    fillValidReport(Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode]))
        ->set('photo', UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'))
        ->call('submit')
        ->assertHasErrors(['photo']);
});

// ── Envío exitoso ──────────────────────────────────────────────────────────────

it('creates an issue report with the mandatory name and cargo', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    fillValidReport(Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode]))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $report = EquipmentIssueReport::withoutGlobalScopes()->where('equipment_id', $equipment->id)->latest()->first();

    expect($report)->not->toBeNull()
        ->and($report->reporter_name)->toBe('Juan Pérez')
        ->and($report->reporter_position)->toBe('Operario de planta')
        ->and($report->status->value)->toBe('open')
        ->and($report->qr_code_id)->toBe($qrCode->id);
});

it('stores a photo taken from the phone', function () {
    Storage::fake('public');
    config(['filesystems.persistent_disk' => 'public']);

    [$equipment, $qrCode] = makeQrWithEquipment();

    fillValidReport(Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode]))
        ->set('photo', UploadedFile::fake()->image('falla.jpg', 800, 600))
        ->call('submit')
        ->assertHasNoErrors();

    $report = EquipmentIssueReport::withoutGlobalScopes()->where('equipment_id', $equipment->id)->latest()->first();

    expect($report->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($report->photo_path);
});

it('creates the report without a photo (it is optional)', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    fillValidReport(Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode]))
        ->call('submit')
        ->assertHasNoErrors();

    $report = EquipmentIssueReport::withoutGlobalScopes()->where('equipment_id', $equipment->id)->latest()->first();

    expect($report->photo_path)->toBeNull();
});

it('resets form fields after successful submission', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    fillValidReport(Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode]))
        ->call('submit')
        ->assertSet('description', '')
        ->assertSet('reporterName', '')
        ->assertSet('reporterPosition', '');
});

// ── Rate limiting ─────────────────────────────────────────────────────────────

it('blocks submission when rate limit is exceeded', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    RateLimiter::clear('issue-report:127.0.0.1');

    $component = Livewire::test(ReportForm::class, ['equipment' => $equipment, 'qrCode' => $qrCode]);

    foreach (range(1, 5) as $_) {
        fillValidReport($component)->call('submit');
        $component->set('submitted', false);
    }

    fillValidReport($component)
        ->call('submit')
        ->assertHasErrors(['description']);

    RateLimiter::clear('issue-report:127.0.0.1');
});
