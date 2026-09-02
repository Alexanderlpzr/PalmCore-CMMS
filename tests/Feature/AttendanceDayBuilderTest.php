<?php

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * De la marca cruda al día clasificado. Aquí ya hay base de datos, así que lo que se
 * prueba no es la aritmética —eso está en HourClassifierTest, sin base de datos— sino el
 * emparejamiento de entradas con salidas, la atribución del jornal al día correcto y las
 * reglas que protegen lo que un supervisor ya firmó.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));

    // Los dos festivos de agosto de 2026.
    Holiday::factory()->on('2026-08-07', 'Batalla de Boyacá')->create(['tenant_id' => $this->tenant->id]);
    Holiday::factory()->on('2026-08-17', 'Asunción de la Virgen')->create(['tenant_id' => $this->tenant->id]);
});

function marcar(Employee $employee, string $cuando, AttendanceDirection $sentido): AttendanceScan
{
    return AttendanceScan::factory()->forEmployee($employee)->create([
        'scanned_at' => Carbon::parse($cuando),
        'direction' => $sentido,
    ]);
}

function turno(Employee $employee, string $entrada, string $salida): void
{
    marcar($employee, $entrada, AttendanceDirection::Entrada);
    marcar($employee, $salida, AttendanceDirection::Salida);
}

function construir(Employee $employee, string $desde = '2026-08-01', string $hasta = '2026-08-30')
{
    return app(AttendanceDayBuilder::class)->buildForEmployee(
        $employee,
        Carbon::parse($desde),
        Carbon::parse($hasta),
    );
}

it('convierte un turno normal en un día propuesto', function (): void {
    turno($this->employee, '2026-08-10 06:00', '2026-08-10 14:00');

    $dias = construir($this->employee);

    expect($dias)->toHaveCount(1);

    $dia = $dias->first();

    expect($dia->work_date->toDateString())->toBe('2026-08-10')
        ->and((float) $dia->worked_hours)->toBe(8.0)
        ->and((float) $dia->ordinary_hours)->toBe(8.0)
        ->and($dia->status)->toBe(AttendanceDayStatus::Propuesta)
        ->and($dia->hasAnomalies())->toBeFalse()
        ->and($dia->jornal())->toBe(1);
});

it('atribuye el turno de noche al día en que arrancó, y las horas al día que les toca', function (): void {
    // Sábado 22 a las 22:00 → domingo 23 a las 06:00. El jornal es del sábado, pero seis
    // de las ocho horas son dominicales. Las dos cosas conviven en la misma fila.
    turno($this->employee, '2026-08-22 22:00', '2026-08-23 06:00');

    $dia = construir($this->employee)->first();

    expect($dia->work_date->toDateString())->toBe('2026-08-22')
        ->and((float) $dia->night_surcharge_hours)->toBe(2.0)
        ->and((float) $dia->night_sunday_surcharge_hours)->toBe(6.0)
        ->and((float) $dia->worked_hours)->toBe(8.0);
});

it('paga el festivo con recargo dominical', function (): void {
    turno($this->employee, '2026-08-07 06:00', '2026-08-07 14:00');

    $dia = construir($this->employee)->first();

    expect((float) $dia->sunday_surcharge_hours)->toBe(8.0)
        ->and((float) $dia->ordinary_hours)->toBe(0.0);
});

it('junta las dos sesiones del mismo día en una sola fila', function (): void {
    turno($this->employee, '2026-08-10 06:00', '2026-08-10 12:00');
    turno($this->employee, '2026-08-10 13:00', '2026-08-10 16:00');

    $dias = construir($this->employee);

    expect($dias)->toHaveCount(1)
        ->and((float) $dias->first()->worked_hours)->toBe(9.0)
        ->and((float) $dias->first()->overtime_day_hours)->toBe(1.0);
});

it('anota la entrada que quedó sin salida y no le cuenta horas', function (): void {
    marcar($this->employee, '2026-08-10 06:00', AttendanceDirection::Entrada);

    $dia = construir($this->employee)->first();

    expect((float) $dia->worked_hours)->toBe(0.0)
        ->and($dia->jornal())->toBe(0)
        ->and($dia->anomalies)->toHaveCount(1)
        ->and($dia->anomalies[0])->toContain('sin salida registrada');
});

it('avisa cuando las extras del día pasan del tope legal', function (): void {
    // 06:00 a 20:00: catorce horas, seis de ellas extras. El tope es dos.
    turno($this->employee, '2026-08-10 06:00', '2026-08-10 20:00');

    $dia = construir($this->employee)->first();

    expect($dia->hasAnomalies())->toBeTrue()
        ->and(implode(' ', $dia->anomalies))->toContain('tope legal')
        // Avisa, pero no recorta: el trabajo ya ocurrió y no pagarlo no lo deshace.
        ->and($dia->hours()->overtimeHours())->toBe(6.0);
});

it('al trabajador de dirección le cuenta las horas pero no le paga recargos', function (): void {
    // Es la protección más importante del módulo: 14 de los 48 de la extractora están en
    // este caso, y sin ella el reloj les generaría extras que no se deben pagar.
    $supervisor = Employee::factory()->supervisor()->create(['tenant_id' => $this->tenant->id]);

    turno($supervisor, '2026-08-22 22:00', '2026-08-23 08:00');

    $dia = construir($supervisor)->first();

    expect((float) $dia->worked_hours)->toBe(10.0)
        ->and((float) $dia->ordinary_hours)->toBe(10.0)
        ->and($dia->hours()->overtimeHours())->toBe(0.0)
        ->and((float) $dia->night_sunday_surcharge_hours)->toBe(0.0)
        ->and(implode(' ', $dia->anomalies))->toContain('Sin recargos ni extras');
});

it('reconstruir sobrescribe la propuesta en vez de duplicarla', function (): void {
    turno($this->employee, '2026-08-10 06:00', '2026-08-10 14:00');
    construir($this->employee);

    // Llega una marca que corrige la salida.
    marcar($this->employee, '2026-08-10 16:00', AttendanceDirection::Salida);
    $dias = construir($this->employee);

    expect($dias)->toHaveCount(1)
        ->and(AttendanceDay::query()->forTenant($this->tenant->id)->count())->toBe(1);
});

it('no toca un día que un supervisor ya firmó', function (): void {
    turno($this->employee, '2026-08-10 06:00', '2026-08-10 14:00');
    $dia = construir($this->employee)->first();

    $supervisor = User::factory()->create();
    app(AttendanceDayConfirmer::class)->confirm($dia, $supervisor);

    // Llega una marca nueva y se reconstruye: la fila firmada se respeta.
    marcar($this->employee, '2026-08-10 18:00', AttendanceDirection::Salida);
    $reconstruidos = construir($this->employee);

    expect($reconstruidos)->toBeEmpty()
        ->and((float) $dia->fresh()->worked_hours)->toBe(8.0)
        ->and($dia->fresh()->status)->toBe(AttendanceDayStatus::Confirmada);
});

it('firmar deja el autor y la hora', function (): void {
    turno($this->employee, '2026-08-10 06:00', '2026-08-10 14:00');
    $dia = construir($this->employee)->first();
    $supervisor = User::factory()->create();

    $firmado = app(AttendanceDayConfirmer::class)->confirm($dia, $supervisor);

    expect($firmado->status)->toBe(AttendanceDayStatus::Confirmada)
        ->and($firmado->confirmed_by)->toBe($supervisor->id)
        ->and($firmado->confirmed_at)->not->toBeNull()
        ->and($firmado->status->isPayable())->toBeTrue();
});

it('reabrir un día firmado lo devuelve a propuesta y borra la firma', function (): void {
    turno($this->employee, '2026-08-10 06:00', '2026-08-10 14:00');
    $dia = construir($this->employee)->first();
    $confirmer = app(AttendanceDayConfirmer::class);
    $confirmer->confirm($dia, User::factory()->create());

    $reabierto = $confirmer->reopen($dia);

    expect($reabierto->status)->toBe(AttendanceDayStatus::Propuesta)
        ->and($reabierto->confirmed_by)->toBeNull()
        ->and($reabierto->confirmed_at)->toBeNull();
});

it('detecta cuando las extras de la semana pasan del tope, aunque ningún día lo pase', function (): void {
    // Tres horas extras diarias de lunes a sábado: ningún día llega a doce, la semana sí.
    foreach (['10', '11', '12', '13', '14', '15'] as $dia) {
        turno($this->employee, "2026-08-{$dia} 06:00", "2026-08-{$dia} 17:00");
    }

    construir($this->employee);

    $excesos = app(AttendanceDayBuilder::class)->weeklyOvertimeBreaches(
        $this->tenant->id,
        $this->employee->id,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-30'),
    );

    expect($excesos)->toHaveCount(1)
        ->and($excesos->first()['hours'])->toBe(18.0)
        ->and($excesos->first()['limit'])->toBe(12.0);
});

it('reconstruye a toda la empresa de una pasada', function (): void {
    $otro = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    turno($this->employee, '2026-08-10 06:00', '2026-08-10 14:00');
    turno($otro, '2026-08-10 14:00', '2026-08-10 22:00');

    $dias = app(AttendanceDayBuilder::class)->buildForTenant(
        $this->tenant->id,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-30'),
    );

    expect($dias)->toHaveCount(2);
});

it('no arrastra el turno del mes anterior al período que se reconstruye', function (): void {
    // Turno que arranca el 31 de julio y termina el 1 de agosto. Su jornal es de julio y
    // no debe aparecer al reconstruir agosto.
    turno($this->employee, '2026-07-31 22:00', '2026-08-01 06:00');
    turno($this->employee, '2026-08-03 06:00', '2026-08-03 14:00');

    $dias = construir($this->employee);

    expect($dias)->toHaveCount(1)
        ->and($dias->first()->work_date->toDateString())->toBe('2026-08-03');
});
