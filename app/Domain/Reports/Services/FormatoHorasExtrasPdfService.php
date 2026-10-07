<?php

namespace App\Domain\Reports\Services;

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Domain\HumanResources\Services\OvertimeBonusSplitter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use App\Models\Holiday;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * El formato de horas extras de un trabajador (TH-FOR-002), hecho por el sistema.
 *
 * El de Excel se llena a mano: alguien mira la hora de entrada y de salida y reparte las
 * horas en sus columnas, en una hoja copiada por trabajador que con los años terminó en
 * diecinueve diseños distintos. Aquí las horas salen de las marcas de la puerta y de la
 * clasificación del reloj, y el reparto entre horas extras y bonificación, de la misma
 * regla con que se liquida la nómina: el papel y el desprendible no pueden decir cosas
 * distintas. Se imprime para que el jefe inmediato lo firme, como el de siempre.
 */
class FormatoHorasExtrasPdfService
{
    /** Las columnas del formato, en su orden, con la sigla con que las conoce la planta. */
    public const COLUMNS = [
        'overtime_day' => 'HED',
        'overtime_night' => 'HEN',
        'overtime_sunday_day' => 'HEDD',
        'overtime_sunday_night' => 'HEDN',
        'sunday_surcharge' => 'RDD',
        'night_surcharge' => 'RN',
        'night_sunday_surcharge' => 'RND',
    ];

    /** El nombre completo de cada columna, como en el encabezado de la hoja. */
    public const COLUMN_TITLES = [
        'overtime_day' => 'HORAS EXTRAS DIURNAS',
        'overtime_night' => 'HORAS EXTRAS NOCTURNAS',
        'overtime_sunday_day' => 'HORAS EXTRAS DOMINICAL DIURNA',
        'overtime_sunday_night' => 'HORAS EXTRAS DOMINICAL NOCTURNA',
        'sunday_surcharge' => 'RECARGO DIURNO DOMINICAL',
        'night_surcharge' => 'RECARGO NOCTURNO',
        'night_sunday_surcharge' => 'RECARGO NOCTURNO DOMINICAL',
    ];

    /**
     * El control documental del formato, tal como lo imprime la extractora en su hoja: el
     * papel que se firma tiene que ser reconocible como el mismo formato de siempre.
     */
    public const FORM_CODE = 'TH-FOR-002';

    public const FORM_VERSION = '02';

    public const FORM_DATE = '27/11/2020';

    public function __construct(
        private readonly ReportBrandingService $branding,
        private readonly PayrollParameterService $parameters,
        private readonly OvertimeBonusSplitter $splitter,
        private readonly AttendanceCorrectionService $corrections,
    ) {}

    /**
     * @param  bool  $showValues  la calculadora con el salario y los valores; quien no ve
     *                            sueldos recibe el formato solo con las horas
     */
    public function generate(Employee $employee, CarbonInterface $from, CarbonInterface $to, bool $showValues = true): string
    {
        $tenant = Tenant::withoutGlobalScopes()->find($employee->tenant_id);
        $documentNumber = sprintf('THF-%s-%s', CarbonImmutable::instance($to)->format('Ym'), $employee->document_number);

        return Pdf::loadView('reports.formato-horas-extras', $this->data($employee, $from, $to) + [
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64(
                $this->branding->documentIdentityPayload($documentNumber, $tenant),
            ),
            'generatedAt' => now(),
            'showValues' => $showValues,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(Employee $employee, CarbonInterface $to): string
    {
        return sprintf('formato-horas-%s-%s.pdf', $employee->document_number, CarbonImmutable::instance($to)->format('Y-m'));
    }

    /**
     * Todo lo que va en el formato, listo para pintar.
     *
     * El mes que se paga es el del último día de la ventana: las horas del 27 de
     * septiembre al 26 de octubre van en la nómina de octubre, y los días de septiembre
     * son los que la regla del bono manda enteros a bonificación.
     *
     * @return array<string, mixed>
     */
    public function data(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::parse(CarbonImmutable::instance($from)->toDateString());
        $to = CarbonImmutable::parse(CarbonImmutable::instance($to)->toDateString());
        $periodStart = $to->startOfMonth();
        $tenantId = $employee->tenant_id;
        $timezone = Tenant::withoutGlobalScopes()->find($tenantId)?->plantTimezone() ?? 'America/Bogota';

        $p = $this->parameters->allOn($periodStart, $tenantId);
        $bonusRule = $this->parameters->isOn(PayrollParameter::OvertimeExcessAsBonus, $periodStart, $tenantId);
        $salary = (float) $employee->base_salary;
        $divisor = (float) ($p[PayrollParameter::MonthlyHoursDivisor->value] ?? 0);
        $hourValue = $divisor > 0 ? $salary / $divisor : 0.0;

        $days = AttendanceDay::query()
            ->forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->between($from->toDateString(), $to->toDateString())
            ->get()
            ->keyBy(fn (AttendanceDay $day): string => $day->work_date->toDateString());

        $novelties = EmployeeNovelty::query()
            ->forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->overlapping($from, $to)
            ->get();

        $holidays = Holiday::query()
            ->forTenant($tenantId)
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('name', 'holiday_date')
            ->mapWithKeys(fn (string $name, $date): array => [CarbonImmutable::parse($date)->toDateString() => $name])
            ->all();

        $rows = [];
        $legalTotal = ClassifiedHours::empty();
        $bonusTotal = ClassifiedHours::empty();

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $day = $days[$key] ?? null;
            [$legal, $bonus] = $day ? $this->split($day, $key < $periodStart->toDateString(), $bonusRule, $p) : [ClassifiedHours::empty(), ClassifiedHours::empty()];
            [$entry, $exit] = $day ? $this->entryAndExit($day, $timezone) : [null, null];

            $scheduledEnd = $entry && ! $day->rest_day_worked
                ? $entry->addMinutes((int) round($this->parameters->valueOn(PayrollParameter::OrdinaryHoursPerDay, $date, $tenantId) * 60))
                : null;

            $legalTotal = $legalTotal->plus($legal);
            $bonusTotal = $bonusTotal->plus($bonus);

            $rows[] = [
                'date' => $date,
                'surcharged' => $date->isSunday() || isset($holidays[$key]),
                'entry' => $entry,
                'scheduledEnd' => $scheduledEnd,
                'exit' => $exit,
                'legal' => $this->columns($legal),
                'bonus' => $this->columns($bonus),
                'notes' => collect([
                    $holidays[$key] ?? null,
                    $novelties->first(fn (EmployeeNovelty $n): bool => $n->starts_on->toDateString() <= $key && $n->ends_on->toDateString() >= $key)?->type->label(),
                    $day?->rest_day_worked ? 'Descanso trabajado' : null,
                    $day?->maintenance_support ? 'Apoyo a mantenimiento' : null,
                    $day?->isManuallyAdjusted() ? 'Ajustado a mano: '.$day->adjustment_reason : null,
                    $day?->status === AttendanceDayStatus::Propuesta ? 'Sin confirmar' : null,
                ])->filter()->implode(' · '),
            ];
        }

        $calculator = [];

        foreach ($legalTotal->paidBuckets() as $bucket => $paid) {
            $factor = (float) ($p[$paid['parameter']->value] ?? 0);
            $rate = $hourValue * $factor;
            $bonusHours = $this->columns($bonusTotal)[$bucket];

            $calculator[$bucket] = [
                'label' => self::COLUMNS[$bucket],
                'concept' => $paid['parameter']->label(),
                'factor' => $factor,
                'rate' => $rate,
                'legalHours' => $paid['hours'],
                'legalAmount' => round($paid['hours'] * $rate, 2),
                'bonusHours' => $bonusHours,
                'bonusAmount' => round($bonusHours * $rate, 2),
            ];
        }

        return [
            'employee' => $employee,
            'supervisor' => $employee->immediate_supervisor,
            'from' => $from,
            'to' => $to,
            'periodStart' => $periodStart,
            'rows' => $rows,
            'legalTotals' => $this->columns($legalTotal),
            'bonusTotals' => $this->columns($bonusTotal),
            'bonusColumns' => array_keys(array_filter($this->columns($bonusTotal), fn (float $hours): bool => $hours > 0)),
            'bonusRule' => $bonusRule,
            'salary' => $salary,
            'divisor' => $divisor,
            'hourValue' => $hourValue,
            'calculator' => $calculator,
            'legalAmount' => array_sum(array_column($calculator, 'legalAmount')),
            'bonusAmount' => array_sum(array_column($calculator, 'bonusAmount')),
            'unconfirmed' => $days->filter(fn (AttendanceDay $d): bool => $d->status === AttendanceDayStatus::Propuesta)->count(),
        ];
    }

    /**
     * @param  array<string, float>  $p
     * @return array{0: ClassifiedHours, 1: ClassifiedHours} horas extras y recargos, bonificación
     */
    private function split(AttendanceDay $day, bool $previousMonth, bool $bonusRule, array $p): array
    {
        if (! $bonusRule) {
            return [$day->hours(), ClassifiedHours::empty()];
        }

        $parts = $this->splitter->split(
            $day->hours(),
            wholeDayAsBonus: $previousMonth,
            dailyCap: (float) ($p[PayrollParameter::MaxOvertimeHoursDay->value] ?? 0),
            isRestDay: (bool) $day->rest_day_worked,
        );

        return [$parts['legal'], $parts['bonus']];
    }

    /**
     * La primera entrada y la última salida del día, en la hora de la planta.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function entryAndExit(AttendanceDay $day, string $timezone): array
    {
        $marks = $this->corrections->marksOfDay($day);
        $local = fn (?AttendanceScan $mark): ?CarbonImmutable => $mark ? CarbonImmutable::instance($mark->scanned_at)->setTimezone($timezone) : null;

        return [
            $local($marks->first(fn (AttendanceScan $m): bool => $m->direction === AttendanceDirection::Entrada)),
            $local($marks->last(fn (AttendanceScan $m): bool => $m->direction === AttendanceDirection::Salida)),
        ];
    }

    /** @return array<string, float> las horas en las columnas del formato */
    private function columns(ClassifiedHours $hours): array
    {
        $columns = [];

        foreach ($hours->paidBuckets() as $bucket => $paid) {
            $columns[$bucket] = round($paid['hours'], 4);
        }

        return array_merge(array_fill_keys(array_keys(self::COLUMNS), 0.0), $columns);
    }
}
