<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\OvertimeParallel;
use App\Domain\HumanResources\Services\OvertimeWorkbookReader;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\Employee;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/*
 * El paralelo del libro de horas extras (TH-NOM-P-001): un libro de prueba con la forma
 * del de la extractora —encabezado en la fila 9, fechas escritas como texto, «BONO RDN»,
 * las «12:00pm» que son medianoche, la fila de totales sin fecha— y la comparación día por
 * día contra lo que calcula el sistema con la misma hora de entrada y de salida.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    $parametros = app(PayrollParameterService::class);
    $parametros->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));

    foreach ([
        [PayrollParameter::MonthlyHoursDivisor, 210],
        [PayrollParameter::OrdinaryHoursPerDay, 7],
        [PayrollParameter::NightWindowStart, 19],
        [PayrollParameter::HoursCutoffDay, 26],
        [PayrollParameter::OvertimeExcessAsBonus, 1],
    ] as [$parametro, $valor]) {
        $parametros->setValue($parametro, $valor, Carbon::parse('2026-01-01'), $this->tenant->id);
    }

    $this->employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'document_number' => '1.116.615.860',
        'base_salary' => 2_100_000,
    ]);

    $this->libro = libroDeHorasDePrueba();
});

afterEach(function (): void {
    @unlink($this->libro);
});

/** Una fila del formato: fecha, horas y, por columna, lo que se anotó. */
function filaDelFormato(string $fecha, string $inicio = '', string $fin = '', string $salida = '', array $horas = [], string $justificacion = ''): array
{
    // A FECHA · B-D horas · E-K las siete legales · L justificación · O-R los bonos.
    $columnas = ['HED' => 4, 'HEN' => 5, 'HEDD' => 6, 'HEDN' => 7, 'HDOM' => 8, 'RN' => 9, 'RND' => 10, 'bHEN' => 14, 'bHED' => 15, 'bRN' => 16, 'bRDN' => 17];
    $fila = array_fill(0, 18, '');
    [$fila[0], $fila[1], $fila[2], $fila[3], $fila[11]] = [$fecha, $inicio, $fin, $salida, $justificacion];

    foreach ($horas as $columna => $valor) {
        $fila[$columnas[$columna]] = $valor;
    }

    return $fila;
}

function libroDeHorasDePrueba(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'horas').'.xlsx';
    $encabezado = ['FECHA', 'HORA INICIO', 'FIN HORARIO LABORAL', 'HORA      SALIDA', 'HORAS EXTRAS DIURNAS', 'HORAS EXTRAS NOCTURNAS',
        'HORAS EXTRAS DOMINICAL DIURNA', 'HORAS EXTRAS DOMINICAL NOCTURNA', 'HORA DOMINICAL', 'RECARGO NOCTURNO', 'RECARGO NOCTURNO DOMINICAL',
        'JUSTIFICACIÓN ', '', '', 'BONO HEN', 'BONO HED', 'BONO RN', 'BONO RDN'];

    $hoja = function (Writer $writer, array $dias, bool $conTotales = true) use ($encabezado): void {
        $writer->addRow(Row::fromValues(['', '', '', 'FORMATO DE HORAS EXTRAS ']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['NOMBRE TRABAJADOR', 'PEDRO PÉREZ', '', '', '', '', '', '', '', '', '', '', 'IDENTIFICACIÓN', '', 1116615860]));
        $writer->addRow(Row::fromValues(['CARGO', 'MECÁNICO I']));
        $writer->addRow(Row::fromValues(['JEFE INMEDIATO', 'SEBASTIÁN SÁNCHEZ']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([...$encabezado, '', 'SALARIO', 2_100_000]));

        foreach ($dias as $dia) {
            $writer->addRow(Row::fromValues($dia));
        }

        if ($conTotales) {
            // La fila de las SUM: sin fecha, no es un día.
            $writer->addRow(Row::fromValues(['', '', '', '', 7, 1, 8, 0, 0, 4, 0, '', '', '', 0, 6, 0, 0]));
            $writer->addRow(Row::fromValues(['Total Horas Extras Diurnas', '', '', '', '', 7]));
            $writer->addRow(Row::fromValues(['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'TOTAL BONO', 75_000]));
        }
    };

    $writer = new Writer;
    $writer->openToFile($ruta);
    $writer->getCurrentSheet()->setName('PEDRO');
    $hoja($writer, [
        filaDelFormato('domingo, 27 de septiembre 2026'),
        // Del mes anterior: entero a bonificación.
        filaDelFormato('lunes, 28 de septiembre de 2026', '7:00am', '2:00pm', '6:00pm', ['bHED' => 4], 'Turno día'),
        filaDelFormato('lunes, 5 de octubre de 2026', '7:00am', '2:00pm', '6:00pm', ['HED' => 2, 'bHED' => 2], 'Turno día'),
        // Domingo de mantenimiento: entero como extra dominical.
        filaDelFormato('domingo, 11 de octubre de 2026', '7:00am', '3:00pm', '3:00pm', ['HEDD' => 8], 'Mantenimiento'),
        // Dice jueves y era viernes; y «12:00pm» es la medianoche.
        filaDelFormato('jueves, 16 de octubre de 2026', '4:00pm', '11:00pm', '12:00pm', ['RN' => 4, 'HEN' => 1], 'Turno noche'),
        // Tres extras como horas extras: el tope era de dos.
        filaDelFormato('martes, 20 de octubre de 2026', '7:00am', '2:00pm', '5:00pm}', ['HED' => 3], 'Turno día'),
    ]);

    $oculta = $writer->addNewSheetAndMakeItCurrent();
    $oculta->setName('VIEJO')->setIsVisible(false);
    $hoja($writer, [filaDelFormato('lunes, 5 de octubre de 2026', '7:00am', '2:00pm', '6:00pm', ['HED' => 4])], conTotales: false);

    $writer->addNewSheetAndMakeItCurrent()->setName('BONIFICACIONES');
    $writer->addRow(Row::fromValues(['BONIFICACIÓN CONSTITUTIVA']));

    $writer->close();

    return $ruta;
}

it('reads only the visible worker sheets, with the days that carry their date', function (): void {
    $hojas = app(OvertimeWorkbookReader::class)->read($this->libro);

    expect($hojas)->toHaveCount(1)
        ->and($hojas[0]['sheet'])->toBe('PEDRO')
        ->and($hojas[0]['document'])->toBe('1116615860')
        ->and($hojas[0]['salary'])->toBe(2_100_000.0)
        ->and($hojas[0]['bonusTotal'])->toBe(75_000.0)
        ->and(array_map(fn (array $d): string => $d['date']->toDateString(), $hojas[0]['days']))
        ->toBe(['2026-09-28', '2026-10-05', '2026-10-11', '2026-10-16', '2026-10-20'])
        ->and($hojas[0]['days'][3]['exit'])->toBe('12:00')
        ->and($hojas[0]['days'][0]['bonus']['overtime_day'])->toBe(4.0);
});

it('compares each day with what the system computes from the same times', function (): void {
    $hojas = app(OvertimeWorkbookReader::class)->read($this->libro);
    [$pedro] = app(OvertimeParallel::class)->compare($hojas, $this->tenant, CarbonImmutable::parse('2026-10-01'));
    $dia = fn (string $fecha): array => collect($pedro['days'])->first(fn (array $d): bool => $d['date']->toDateString() === $fecha);

    expect($pedro['found'])->toBeTrue()
        ->and($dia('2026-09-28')['differs'])->toBeFalse()
        ->and($dia('2026-10-05')['differs'])->toBeFalse()
        // El domingo entero como extra se compara como descanso trabajado.
        ->and($dia('2026-10-11')['system']['restDay'])->toBeTrue()
        ->and($dia('2026-10-11')['differs'])->toBeFalse()
        // La medianoche escrita como mediodía: 4 de recargo nocturno y una extra nocturna.
        ->and($dia('2026-10-16')['system']['legal']['night_surcharge'])->toBe(4.0)
        ->and($dia('2026-10-16')['system']['legal']['overtime_night'])->toBe(1.0)
        ->and($dia('2026-10-16')['differs'])->toBeFalse()
        // El sistema deja dos de horas extras y manda la tercera a bonificación.
        ->and($dia('2026-10-20')['system']['legal']['overtime_day'])->toBe(2.0)
        ->and($dia('2026-10-20')['system']['bonus']['overtime_day'])->toBe(1.0)
        ->and($dia('2026-10-20')['differs'])->toBeTrue()
        ->and($pedro['differentDays'])->toBe(1);
});

it('prints the summary and leaves the detail in a workbook', function (): void {
    $salida = sys_get_temp_dir().'/paralelo-horas-prueba.xlsx';
    @unlink($salida);

    $this->artisan('payroll:overtime-parallel', ['file' => $this->libro, '--tenant' => $this->tenant->slug, '--output' => $salida])
        ->expectsOutputToContain('nómina de octubre de 2026')
        ->expectsOutputToContain('Días comparados: 5. Iguales: 4. Distintos: 1.')
        ->assertSuccessful();

    expect(file_exists($salida))->toBeTrue();
    @unlink($salida);
});

it('reads the times as they are written in the book', function (mixed $escrita, ?string $hora): void {
    expect(OvertimeWorkbookReader::parseTime($escrita))->toBe($hora);
})->with([
    ['6:00pm', '18:00'],
    ['6:00am', '06:00'],
    ['4:00pm ', '16:00'],
    ['12:00pm', '12:00'],
    ['12:00am', '00:00'],
    ['5:00pm}', '17:00'],
    ['7:00 a. m.', '07:00'],
    [0.25, '06:00'],
    ['', null],
]);

it('reads the date from the day and the month, never from the weekday', function (string $escrita, ?string $fecha): void {
    expect(OvertimeWorkbookReader::parseDate($escrita, null)?->toDateString())->toBe($fecha);
})->with([
    ['domingo,19  de octubre de 2026', '2026-10-19'],
    ['miércoles, 14 deoctubre de 2026', '2026-10-14'],
    ['domingo, 27 de septiembre 2026', '2026-09-27'],
    ['jueves, 16 de octubre de 2026', '2026-10-16'],
    ['Total Horas Extras Diurnas', null],
]);
