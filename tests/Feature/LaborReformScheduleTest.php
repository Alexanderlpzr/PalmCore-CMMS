<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\PayrollParameterException;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\PayrollParameterVersion;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/*
 * La extractora tenía cargados los valores de antes de la reforma mientras su formato de
 * horas extras ya liquidaba con los nuevos. El comando los carga como vigencias, cada uno
 * desde la fecha de su norma, sin tocar lo anterior.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));
});

function reformValue(PayrollParameter $parameter, string $on, string $tenantId): float
{
    return app(PayrollParameterService::class)->valueOn($parameter, Carbon::parse($on), $tenantId);
}

it('only shows the plan without --apply', function (): void {
    $before = PayrollParameterVersion::query()->forTenant($this->tenant->id)->count();

    $this->artisan('payroll:apply-labor-reform', ['--tenant' => $this->tenant->slug, '--until' => '2026-10-06'])
        ->expectsOutputToContain('plan: no se escribió nada')
        ->assertSuccessful();

    expect(PayrollParameterVersion::query()->forTenant($this->tenant->id)->count())->toBe($before)
        ->and(reformValue(PayrollParameter::NightWindowStart, '2026-10-06', $this->tenant->id))->toBe(21.0);
});

it('loads each change of the reform from the date of its law', function (): void {
    $this->artisan('payroll:apply-labor-reform', ['--tenant' => $this->tenant->slug, '--until' => '2026-10-06', '--apply' => true])
        ->assertSuccessful();

    $id = $this->tenant->id;

    // La noche desde las 7 p. m. rige desde el 25/12/2025: antes de la primera vigencia.
    expect(reformValue(PayrollParameter::NightWindowStart, '2026-01-01', $id))->toBe(19.0)
        // Dominical del 90 % desde el 1 de julio, con sus tres derivados.
        ->and(reformValue(PayrollParameter::SurchargeSunday, '2026-06-30', $id))->toBe(0.80)
        ->and(reformValue(PayrollParameter::SurchargeSunday, '2026-07-01', $id))->toBe(0.90)
        ->and(reformValue(PayrollParameter::SurchargeNightSunday, '2026-07-01', $id))->toBe(1.25)
        ->and(reformValue(PayrollParameter::OvertimeSundayDay, '2026-07-01', $id))->toBe(2.15)
        ->and(reformValue(PayrollParameter::OvertimeSundayNight, '2026-07-01', $id))->toBe(2.65)
        // 42 horas desde el 15 de julio: divisor 210 y siete horas en seis días.
        ->and(reformValue(PayrollParameter::MonthlyHoursDivisor, '2026-07-14', $id))->toBe(220.0)
        ->and(reformValue(PayrollParameter::MonthlyHoursDivisor, '2026-07-15', $id))->toBe(210.0)
        ->and(reformValue(PayrollParameter::OrdinaryHoursPerDay, '2026-07-15', $id))->toBe(7.0)
        // El del 100 % es de julio de 2027: todavía no entra.
        ->and(reformValue(PayrollParameter::SurchargeSunday, '2027-12-31', $id))->toBe(0.90)
        ->and(app(PayrollParameterService::class)->inconsistentSundayFactors(Carbon::parse('2026-07-01'), $id))->toBe([]);
});

it('does nothing the second time', function (): void {
    $arguments = ['--tenant' => $this->tenant->slug, '--until' => '2026-10-06', '--apply' => true];

    $this->artisan('payroll:apply-labor-reform', $arguments)->assertSuccessful();
    $after = PayrollParameterVersion::query()->forTenant($this->tenant->id)->count();

    $this->artisan('payroll:apply-labor-reform', $arguments)
        ->doesntExpectOutputToContain('aplicado')
        ->assertSuccessful();

    expect(PayrollParameterVersion::query()->forTenant($this->tenant->id)->count())->toBe($after);
});

it('loads the 100 % Sunday surcharge once July 2027 arrives', function (): void {
    $this->artisan('payroll:apply-labor-reform', ['--tenant' => $this->tenant->slug, '--until' => '2027-07-01', '--apply' => true])
        ->assertSuccessful();

    expect(reformValue(PayrollParameter::SurchargeSunday, '2027-06-30', $this->tenant->id))->toBe(0.90)
        ->and(reformValue(PayrollParameter::SurchargeSunday, '2027-07-01', $this->tenant->id))->toBe(1.00)
        ->and(reformValue(PayrollParameter::OvertimeSundayNight, '2027-07-01', $this->tenant->id))->toBe(2.75);
});

it('spreads the 42 hours over the days the company works', function (): void {
    $this->artisan('payroll:apply-labor-reform', ['--tenant' => $this->tenant->slug, '--until' => '2026-10-06', '--work-days' => 5, '--apply' => true])
        ->assertSuccessful();

    expect(reformValue(PayrollParameter::OrdinaryHoursPerDay, '2026-07-15', $this->tenant->id))->toBe(8.4);
});

it('seeds the cutoff day and the bonus rule turned off', function (): void {
    expect(reformValue(PayrollParameter::HoursCutoffDay, '2026-10-06', $this->tenant->id))->toBe(0.0)
        ->and(reformValue(PayrollParameter::OvertimeExcessAsBonus, '2026-10-06', $this->tenant->id))->toBe(0.0);
});

it('rejects a cutoff day that does not exist every month, and a rule that is not yes or no', function (PayrollParameter $parameter, float $value): void {
    expect(fn () => app(PayrollParameterService::class)->setValue($parameter, $value, Carbon::parse('2026-10-01'), $this->tenant->id))
        ->toThrow(PayrollParameterException::class);
})->with([
    'corte el 29' => [PayrollParameter::HoursCutoffDay, 29],
    'regla en 2' => [PayrollParameter::OvertimeExcessAsBonus, 2],
]);
