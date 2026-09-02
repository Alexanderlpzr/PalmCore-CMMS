<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\PayrollParameterException;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\PayrollParameterVersion;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/*
 * Lo que se prueba aquí es una sola propiedad, y es la que justifica todo el módulo:
 * cambiar un parámetro no puede cambiar el resultado de una nómina ya liquidada.
 */

function payrollService(): PayrollParameterService
{
    return app(PayrollParameterService::class);
}

it('devuelve el valor que regía en la fecha, no el de hoy', function (): void {
    $tenant = Tenant::factory()->create();
    $service = payrollService();

    // El recargo dominical valió 0,80 hasta junio y 0,90 desde julio.
    $service->setValue(PayrollParameter::SurchargeSunday, 0.80, Carbon::parse('2026-01-01'), $tenant->id);
    $service->setValue(PayrollParameter::SurchargeSunday, 0.90, Carbon::parse('2026-07-01'), $tenant->id);

    expect($service->valueOn(PayrollParameter::SurchargeSunday, Carbon::parse('2026-03-15'), $tenant->id))->toBe(0.80)
        ->and($service->valueOn(PayrollParameter::SurchargeSunday, Carbon::parse('2026-08-31'), $tenant->id))->toBe(0.90);
});

it('cierra el tramo anterior el día antes de que empiece el nuevo', function (): void {
    $tenant = Tenant::factory()->create();
    $service = payrollService();

    $service->setValue(PayrollParameter::Smlmv, 1_423_500, Carbon::parse('2026-01-01'), $tenant->id);
    $service->setValue(PayrollParameter::Smlmv, 1_750_905, Carbon::parse('2026-07-01'), $tenant->id);

    $closed = PayrollParameterVersion::query()
        ->forTenant($tenant->id)
        ->where('key', PayrollParameter::Smlmv->value)
        ->whereNotNull('effective_to')
        ->first();

    expect($closed->effective_to->toDateString())->toBe('2026-06-30');
});

it('no deja abrir una vigencia por detrás de otra que ya existe', function (): void {
    $tenant = Tenant::factory()->create();
    $service = payrollService();

    $service->setValue(PayrollParameter::SurchargeNight, 0.35, Carbon::parse('2026-07-01'), $tenant->id);

    // Enero ya se liquidó con lo que hubiera; meter un tramo por detrás lo cambiaría.
    $service->setValue(PayrollParameter::SurchargeNight, 0.30, Carbon::parse('2026-01-01'), $tenant->id);
})->throws(PayrollParameterException::class);

it('corregir el mismo día no crea un tramo nuevo', function (): void {
    $tenant = Tenant::factory()->create();
    $service = payrollService();

    // Se cargó 0,08 por error y se corrige a 0,80 el mismo día: nada se ha liquidado aún.
    $service->setValue(PayrollParameter::SurchargeSunday, 0.08, Carbon::parse('2026-07-01'), $tenant->id);
    $service->setValue(PayrollParameter::SurchargeSunday, 0.80, Carbon::parse('2026-07-01'), $tenant->id);

    $versions = PayrollParameterVersion::query()
        ->forTenant($tenant->id)
        ->where('key', PayrollParameter::SurchargeSunday->value)
        ->get();

    expect($versions)->toHaveCount(1)
        ->and((float) $versions->first()->value)->toBe(0.80);
});

it('falla en vez de inventar un valor cuando no hay vigencia cargada', function (): void {
    $tenant = Tenant::factory()->create();

    payrollService()->valueOn(PayrollParameter::Smlmv, Carbon::parse('2026-08-31'), $tenant->id);
})->throws(PayrollParameterException::class);

it('rechaza un porcentaje escrito donde va un factor', function (): void {
    $tenant = Tenant::factory()->create();

    // 35 en vez de 0,35 multiplicaría por cien la nómina de la planta.
    payrollService()->setValue(PayrollParameter::SurchargeNight, 35, Carbon::parse('2026-01-01'), $tenant->id);
})->throws(PayrollParameterException::class);

it('carga los valores iniciales del libro actual y no los duplica', function (): void {
    $tenant = Tenant::factory()->create();
    $service = payrollService();

    $first = $service->seedDefaults($tenant->id, Carbon::parse('2026-01-01'));
    $second = $service->seedDefaults($tenant->id, Carbon::parse('2026-01-01'));

    expect($first)->toBe(count(PayrollParameter::cases()))
        ->and($second)->toBe(0)
        ->and($service->valueOn(PayrollParameter::MonthlyHoursDivisor, Carbon::parse('2026-08-31'), $tenant->id))->toBe(220.0)
        ->and($service->missingOn(Carbon::parse('2026-08-31'), $tenant->id))->toBeEmpty();
});

it('avisa cuando se cambia la base dominical y se olvidan los factores derivados', function (): void {
    $tenant = Tenant::factory()->create();
    $service = payrollService();
    $date = Carbon::parse('2026-08-31');

    $service->seedDefaults($tenant->id, Carbon::parse('2026-01-01'));

    expect($service->inconsistentSundayFactors($date, $tenant->id))->toBeEmpty();

    // Sube la base al 90% y no toca los tres derivados.
    $service->setValue(PayrollParameter::SurchargeSunday, 0.90, Carbon::parse('2026-07-01'), $tenant->id);

    $problems = $service->inconsistentSundayFactors($date, $tenant->id);

    expect($problems)->toHaveCount(3);

    $byKey = collect($problems)->keyBy(fn (array $p): string => $p['parameter']->value);

    expect($byKey[PayrollParameter::SurchargeNightSunday->value]['expected'])->toBe(1.25)
        ->and($byKey[PayrollParameter::OvertimeSundayDay->value]['expected'])->toBe(2.15)
        ->and($byKey[PayrollParameter::OvertimeSundayNight->value]['expected'])->toBe(2.65);
});

it('mantiene separadas las vigencias de cada empresa', function (): void {
    $uno = Tenant::factory()->create();
    $otro = Tenant::factory()->create();
    $service = payrollService();

    $service->setValue(PayrollParameter::Smlmv, 1_750_905, Carbon::parse('2026-01-01'), $uno->id);
    $service->setValue(PayrollParameter::Smlmv, 1_300_000, Carbon::parse('2026-01-01'), $otro->id);

    expect($service->valueOn(PayrollParameter::Smlmv, Carbon::parse('2026-08-31'), $uno->id))->toBe(1750905.0)
        ->and($service->valueOn(PayrollParameter::Smlmv, Carbon::parse('2026-08-31'), $otro->id))->toBe(1300000.0);
});
