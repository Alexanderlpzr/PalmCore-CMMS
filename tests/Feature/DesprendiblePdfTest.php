<?php

use App\Domain\HumanResources\Enums\BonusType;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\HumanResources\Services\PayrollRunService;
use App\Domain\Reports\Services\DesprendiblePdfService;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeDeduction;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/*
 * El comprobante. Lo que se comprueba no es el aspecto sino la propiedad que lo hace un
 * comprobante y no una consulta: que todo lo que imprime sale del renglón guardado, de
 * modo que cambiar un parámetro o el sueldo del trabajador no reescribe el papel de
 * agosto.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));

    $this->employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'base_salary' => 1_898_894,
        'position' => 'Analista de Laboratorio',
        'first_name' => 'Gerson',
        'last_name' => 'Romero',
    ]);

    $this->run = PayrollRun::factory()
        ->forPeriod('2026-08-01', '2026-08-30', 'Agosto 2026')
        ->create(['tenant_id' => $this->tenant->id]);

    for ($d = 1; $d <= 30; $d++) {
        AttendanceDay::factory()->forEmployee($this->employee)->confirmed()->create([
            'work_date' => sprintf('2026-08-%02d', $d),
            'ordinary_hours' => 8,
            'night_surcharge_hours' => $d === 1 ? 45 : 0,
            'overtime_day_hours' => $d === 1 ? 14 : 0,
            'worked_hours' => 8,
        ]);
    }

    EmployeeBonus::factory()->forEmployee($this->employee)
        ->of(BonusType::Constitutiva, 325_976.80, '2026-08-01', '2026-08-31')->create();

    EmployeeDeduction::factory()->forEmployee($this->employee)->create([
        'concept' => 'Seguro funerario', 'amount' => 12_750, 'effective_from' => '2026-01-01',
    ]);

    app(PayrollRunService::class)->calculate($this->run);

    $this->entry = $this->run->entries()->first();
});

it('genera un PDF con los datos del trabajador y el neto', function (): void {
    $pdf = app(DesprendiblePdfService::class)->generate($this->tenant->id, $this->entry->id);

    expect($pdf)->toBeString()
        ->and(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(strlen($pdf))->toBeGreaterThan(3000);
});

it('nombra el archivo con la cédula y el período', function (): void {
    $nombre = app(DesprendiblePdfService::class)->filename($this->tenant->id, $this->entry->id);

    expect($nombre)->toBe("desprendible-{$this->employee->document_number}-2026-08.pdf");
});

it('imprime lo que quedó guardado, aunque después cambien el sueldo y los parámetros', function (): void {
    // Es la razón de que el renglón lleve el sueldo copiado: si el comprobante se
    // reconstruyera al imprimirlo, subir un sueldo en octubre reescribiría el papel de
    // agosto.
    $netoOriginal = (float) $this->entry->net_pay;
    $salarioOriginal = (float) $this->entry->base_salary;

    $this->employee->update(['base_salary' => 9_000_000, 'position' => 'Gerente']);
    app(PayrollParameterService::class)->setValue(
        PayrollParameter::SurchargeNight,
        0.90,
        Carbon::parse('2026-09-01'),
        $this->tenant->id,
    );

    $entry = $this->entry->fresh();

    expect((float) $entry->base_salary)->toBe($salarioOriginal)
        ->and((float) $entry->net_pay)->toBe($netoOriginal)
        ->and($entry->position)->toBe('Analista de Laboratorio');

    // Y el PDF se sigue generando con eso.
    $pdf = app(DesprendiblePdfService::class)->generate($this->tenant->id, $entry->id);
    expect(substr($pdf, 0, 4))->toBe('%PDF');
});

it('no imprime las bolsas de horas que quedaron en cero', function (): void {
    // Un comprobante con siete renglones vacíos hace más difícil ver los dos que importan.
    $lineas = $this->entry->surchargeLines();

    expect($lineas)->toHaveCount(2)
        ->and(array_column($lineas, 'concept'))
        ->toBe(['Recargo nocturno', 'Hora extra diurna']);
});

it('la tarifa impresa se deduce del valor y las horas', function (): void {
    $nocturno = collect($this->entry->surchargeLines())
        ->firstWhere('concept', 'Recargo nocturno');

    // Valor hora 8.631,34 × 35 % = 3.020,97
    expect($nocturno['hours'])->toBe(45.0)
        ->and(round($nocturno['rate'], 2))->toBe(3020.97);
});
