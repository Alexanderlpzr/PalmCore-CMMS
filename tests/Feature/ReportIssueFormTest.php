<?php

use App\Livewire\Equipment\ReportForm;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\EquipmentQrCode;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

// ── Setup helper ──────────────────────────────────────────────────────────────

function makeQrWithEquipment(): array
{
    $equipment = Equipment::factory()->create();

    $qrCode = EquipmentQrCode::withoutGlobalScopes()->create([
        'equipment_id'  => $equipment->id,
        'tenant_id'     => $equipment->tenant_id,
        'qr_token'      => (string) Str::uuid(),
        'qr_image_path' => null,
        'is_active'     => true,
        'generated_at'  => now(),
        'scan_count'    => 0,
    ]);

    return [$equipment, $qrCode];
}

// ── Form rendering ────────────────────────────────────────────────────────────

it('renders the report form component', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ])
        ->assertStatus(200)
        ->assertSee('Reportar novedad');
});

// ── Validation ────────────────────────────────────────────────────────────────

it('fails validation when description is empty', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ])
        ->set('description', '')
        ->call('submit')
        ->assertHasErrors(['description']);
});

it('fails validation when description is too short', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ])
        ->set('description', 'Corto')
        ->call('submit')
        ->assertHasErrors(['description']);
});

it('fails validation with an invalid severity value', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ])
        ->set('severity', 'extreme')
        ->set('description', 'Descripción suficientemente larga para pasar validación.')
        ->call('submit')
        ->assertHasErrors(['severity']);
});

// ── Successful submission ─────────────────────────────────────────────────────

it('creates an issue report on valid submission', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ])
        ->set('description', 'El motor presenta vibración excesiva en el eje principal.')
        ->set('severity', 'high')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(
        EquipmentIssueReport::withoutGlobalScopes()
            ->where('equipment_id', $equipment->id)
            ->where('status', 'open')
            ->exists()
    )->toBeTrue();
});

it('stores the correct severity on submission', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ])
        ->set('description', 'Fuga visible de aceite en el sello del compresor.')
        ->set('severity', 'critical')
        ->call('submit');

    $report = EquipmentIssueReport::withoutGlobalScopes()
        ->where('equipment_id', $equipment->id)
        ->latest()
        ->first();

    expect($report->severity->value)->toBe('critical');
});

it('links the report to the qr code', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ])
        ->set('description', 'Ruido anormal detectado durante la operación del equipo.')
        ->set('severity', 'medium')
        ->call('submit');

    $report = EquipmentIssueReport::withoutGlobalScopes()
        ->where('equipment_id', $equipment->id)
        ->latest()
        ->first();

    expect($report->qr_code_id)->toBe($qrCode->id);
});

it('resets form fields after successful submission', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ])
        ->set('description', 'Temperatura elevada detectada en el motor principal del sistema.')
        ->set('severity', 'high')
        ->set('reporterName', 'Juan Pérez')
        ->call('submit')
        ->assertSet('description', '')
        ->assertSet('reporterName', '');
});

// ── Rate limiting ─────────────────────────────────────────────────────────────

it('blocks submission when rate limit is exceeded', function () {
    [$equipment, $qrCode] = makeQrWithEquipment();

    RateLimiter::clear('issue-report:127.0.0.1');

    $component = Livewire::test(ReportForm::class, [
        'equipment' => $equipment,
        'qrCode'    => $qrCode,
    ]);

    // Exhaust the rate limit
    foreach (range(1, 5) as $_) {
        $component
            ->set('description', 'Descripción suficientemente larga para pasar la validación del sistema.')
            ->set('severity', 'low')
            ->call('submit');

        $component->set('submitted', false);
    }

    // 6th attempt should be blocked
    $component
        ->set('description', 'Descripción suficientemente larga para pasar la validación del sistema.')
        ->set('severity', 'low')
        ->call('submit')
        ->assertHasErrors(['description']);

    RateLimiter::clear('issue-report:127.0.0.1');
});
