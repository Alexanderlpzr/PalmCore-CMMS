<?php

use App\Models\Tenant;

/*
 * La migración que carga la razón social y la firma de talento humano del formato de
 * horas extras de El Pajuil: una sola vez, sin pisar lo que ya tenga y sin perder los
 * demás ajustes de la empresa.
 */

function migracionDeFormatosDeElPajuil(): object
{
    return require database_path('migrations/2026_10_22_124237_set_el_pajuil_overtime_form_settings.php');
}

it('loads the legal name and the signer of El Pajuil, keeping its other settings', function (): void {
    $pajuil = Tenant::factory()->create(['slug' => 'ELPAJUIL', 'settings' => ['inventory' => ['warehouse_dispatch' => true]]]);
    $otra = Tenant::factory()->create(['slug' => 'otra']);

    migracionDeFormatosDeElPajuil()->up();

    expect($pajuil->refresh()->legalName())->toBe('EXTRACTORA EL PAJUIL CENTRAL DEL CASANARE SAS ZOMAC')
        ->and($pajuil->overtimeFormSigner())->toBe('Danna Fernanda Torres')
        ->and($pajuil->dispatchesPartsFromWarehouse())->toBeTrue()
        ->and($otra->refresh()->overtimeFormSigner())->toBeNull();
});

it('does not overwrite what was already set', function (): void {
    $pajuil = Tenant::factory()->create(['slug' => 'ELPAJUIL', 'settings' => ['human_resources' => ['form_signer' => 'Otra Persona']]]);

    migracionDeFormatosDeElPajuil()->up();

    expect($pajuil->refresh()->overtimeFormSigner())->toBe('Otra Persona')
        ->and($pajuil->legalName())->toBe('EXTRACTORA EL PAJUIL CENTRAL DEL CASANARE SAS ZOMAC');
});
