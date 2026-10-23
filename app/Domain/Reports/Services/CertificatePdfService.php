<?php

namespace App\Domain\Reports\Services;

use App\Domain\HumanResources\Enums\CertificateType;
use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\Tenant;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Los certificados de talento humano en PDF, calcados de sus formatos de Word: la
 * certificación laboral de quien trabaja y los cuatro documentos del egreso (certificación
 * de quien se retiró, retiro de cesantías, paz y salvo y examen de egreso).
 *
 * Los firma quien la empresa tenga puesto para expedirlos —un trabajador de Personal, con
 * su firma escaneada—: hoy el director de planta, mañana talento humano, sin tocar el
 * código. Cada uno que se expide queda en la carpeta del trabajador.
 */
class CertificatePdfService
{
    /** Los días hábiles que tiene quien se retira para el examen de egreso. */
    public const EXIT_EXAM_BUSINESS_DAYS = 5;

    /** La razón social de cada fondo de cesantías, a quien va dirigida la autorización. */
    public const SEVERANCE_FUND_NAMES = [
        'PORVENIR' => 'Sociedad Administradora de Fondos de Pensiones y Cesantías Porvenir S.A.',
        'PROTECCIÓN' => 'Administradora de Fondos de Pensiones y Cesantías Protección S.A.',
        'PROTECCION' => 'Administradora de Fondos de Pensiones y Cesantías Protección S.A.',
        'COLFONDOS' => 'Colfondos S.A. Pensiones y Cesantías',
        'SKANDIA' => 'Skandia Administradora de Fondos de Pensiones y Cesantías S.A.',
        'FONDO NACIONAL DEL AHORRO' => 'Fondo Nacional del Ahorro',
    ];

    /** Las nóminas cerradas que promedia el salario variable de la certificación. */
    public const SALARY_AVERAGE_RUNS = 3;

    /**
     * Qué salario dice la certificación de persona activa: ninguno, el básico de la ficha,
     * el promedio de las últimas nóminas o el que escriba talento humano.
     */
    public const SALARY_MODES = [
        'none' => 'Sin salario',
        'basic' => 'Salario básico',
        'average' => 'Promedio de las últimas tres nóminas',
        'other' => 'Otro valor',
    ];

    /** Lo que el trabajador declara recibido en el paz y salvo. */
    public const DELIVERED_DOCUMENTS = [
        'certificacion' => 'Certificación laboral',
        'examen' => 'Autorización para el examen médico de egreso',
        'cesantias' => 'Autorización de retiro de cesantías',
        'liquidacion' => 'Liquidación de prestaciones sociales',
    ];

    public function __construct(private readonly ReportBrandingService $branding) {}

    /**
     * Lo que el formulario trae puesto para ese certificado, sacado de la ficha.
     *
     * @return array<string, mixed>
     */
    public function defaults(CertificateType $type, Employee $employee, Tenant $tenant, CarbonImmutable $today): array
    {
        $end = $employee->termination_date ? CarbonImmutable::parse($employee->termination_date->toDateString()) : null;

        return [
            'issued_on' => $today->toDateString(),
            'start_date' => $employee->hire_date?->toDateString(),
            'end_date' => $end?->toDateString(),
            'salary_mode' => (float) $employee->base_salary > 0 ? 'basic' : 'none',
            'salary' => null,
            'salary_note' => null,
            'fund_name' => $this->fundName($employee->severance_fund),
            'delivered' => [],
            'delivered_other' => null,
            'exit_exam_provider' => $tenant->certificateSettings()['exit_exam_provider'],
            'exit_exam_deadline' => $end ? $this->exitExamDeadline($tenant, $end)->toDateString() : null,
        ];
    }

    /** El PDF del certificado. */
    public function render(CertificateType $type, Employee $employee, Tenant $tenant, array $options): string
    {
        return Pdf::loadHTML($this->html($type, $employee, $tenant, $options))
            ->setPaper('letter')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'isFontSubsettingEnabled' => true, 'dpi' => 96])
            ->output();
    }

    /** El certificado en HTML, el que se pasa a PDF: el texto que se firma. */
    public function html(CertificateType $type, Employee $employee, Tenant $tenant, array $options): string
    {
        return view('reports.certificado', [
            'type' => $type,
            'employee' => $employee,
            'tenant' => $tenant,
            'settings' => $tenant->certificateSettings(),
            'signer' => $this->signer($tenant),
            'signature' => $this->signatureBase64($tenant),
            'logoBase64' => $this->branding->logoBase64($tenant),
            'o' => $this->normalize($type, $employee, $options),
        ])->render();
    }

    /**
     * Expide el certificado: lo arma, lo guarda en la carpeta del trabajador y deja dicho
     * quién lo expidió. Se niega sin alguien puesto para firmar.
     */
    public function issue(CertificateType $type, Employee $employee, Tenant $tenant, array $options, User $by): EmployeeDocument
    {
        $signer = $this->signer($tenant);

        if (! $signer) {
            throw new BusinessRuleException('Elija primero quién expide los certificados, en «Quién expide».');
        }

        $pdf = $this->render($type, $employee, $tenant, $options);
        $path = 'employee-documents/'.$employee->tenant_id.'/'.$employee->id.'/certificados/'.Str::uuid7().'.pdf';
        Storage::disk(private_files_disk())->put($path, $pdf);

        $issuedOn = CarbonImmutable::parse($options['issued_on'] ?? now($tenant->plantTimezone())->toDateString());

        return EmployeeDocument::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'document_type' => EmployeeDocumentType::Certificado,
            'title' => $type->code().' · '.$type->label().' · '.$issuedOn->format('d/m/Y'),
            'file_path' => $path,
            'file_name' => $this->filename($type, $employee),
            'file_size' => strlen($pdf),
            'mime_type' => 'application/pdf',
            'notes' => 'Expedido por '.$signer->fullName().($signer->position ? ' ('.$signer->position.')' : '').'.',
            'uploaded_by' => $by->id,
        ]);
    }

    /** «TH-EGR-F-001-068. Certificación laboral (retirado) Carlos Enrique Arévalo Cano.pdf», como en Word. */
    public function filename(CertificateType $type, Employee $employee): string
    {
        $code = $type->code().($employee->employee_code ? '-'.$employee->employee_code : '');

        return str_replace(['/', '\\'], '-', $code.'. '.$type->label().' '.$employee->fullName()).'.pdf';
    }

    /** Quien expide: el trabajador que la empresa tiene puesto, si sigue en Personal. */
    public function signer(Tenant $tenant): ?Employee
    {
        $id = $tenant->certificateSettings()['signer_employee_id'];

        return $id ? Employee::query()->forTenant($tenant->id)->whereKey($id)->first() : null;
    }

    /** La firma escaneada de quien expide, lista para la imagen del PDF o de la pantalla. */
    public function signatureBase64(Tenant $tenant): ?string
    {
        $path = $tenant->certificateSettings()['signature_path'];
        $disk = Storage::disk(private_files_disk());

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode((string) $disk->get($path));
    }

    /**
     * El salario variable: el promedio de lo que la nómina le reconoció como salario en sus
     * últimas nóminas cerradas —básico devengado, recargos, horas extras y bonificaciones
     * constitutivas—, sin el auxilio de transporte ni lo que no es salario.
     *
     * @return array{amount: float, runs: list<string>}|null
     */
    public function averageSalary(Employee $employee): ?array
    {
        $entries = PayrollEntry::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($query) => $query->where('status', PayrollRunStatus::Cerrada))
            ->with('payrollRun:id,name,period_start')
            ->get()
            ->sortByDesc(fn (PayrollEntry $entry): string => (string) $entry->payrollRun?->period_start?->toDateString())
            ->take(self::SALARY_AVERAGE_RUNS)
            ->values();

        if ($entries->isEmpty()) {
            return null;
        }

        $salary = fn (PayrollEntry $entry): float => (float) $entry->earned_with_surcharges
            + (float) $entry->bonus_constitutive
            + (float) $entry->hours_bonus_total;

        return [
            'amount' => round($entries->avg($salary), 0),
            'runs' => $entries->map(fn (PayrollEntry $entry): string => (string) $entry->payrollRun?->name)->all(),
        ];
    }

    /** El último día para el examen de egreso: cinco días hábiles después del retiro, sin festivos. */
    public function exitExamDeadline(Tenant $tenant, CarbonInterface $retiredOn): CarbonImmutable
    {
        $day = CarbonImmutable::parse($retiredOn->toDateString());
        $holidays = Holiday::query()
            ->forTenant($tenant->id)
            ->whereBetween('holiday_date', [$day->toDateString(), $day->addDays(30)->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($date): string => CarbonImmutable::instance($date)->toDateString())
            ->flip();

        $counted = 0;

        while ($counted < self::EXIT_EXAM_BUSINESS_DAYS) {
            $day = $day->addDay();

            if (! $day->isWeekend() && ! $holidays->has($day->toDateString())) {
                $counted++;
            }
        }

        return $day;
    }

    /** La razón social del fondo de cesantías de la ficha, o el nombre tal cual. */
    public function fundName(?string $fund): ?string
    {
        $fund = trim((string) $fund);

        return $fund === '' ? null : (self::SEVERANCE_FUND_NAMES[mb_strtoupper($fund)] ?? $fund);
    }

    /**
     * Las fechas como fechas y lo que falta para el tipo, comprobado: los de egreso necesitan
     * la fecha de retiro.
     *
     * @return array<string, mixed>
     */
    private function normalize(CertificateType $type, Employee $employee, array $options): array
    {
        $date = fn (?string $value): ?CarbonImmutable => filled($value) ? CarbonImmutable::parse($value) : null;

        $options['issued_on'] = $date($options['issued_on'] ?? null) ?? CarbonImmutable::today();
        $options['start_date'] = $date($options['start_date'] ?? null) ?? ($employee->hire_date ? CarbonImmutable::parse($employee->hire_date->toDateString()) : null);
        $options['end_date'] = $date($options['end_date'] ?? null);
        $options['exit_exam_deadline'] = $date($options['exit_exam_deadline'] ?? null);
        $options['salary'] = match ($type === CertificateType::ActiveEmployment ? ($options['salary_mode'] ?? 'basic') : 'none') {
            'basic' => (float) $employee->base_salary > 0 ? (float) $employee->base_salary : null,
            'average' => $this->averageSalary($employee)['amount'] ?? null,
            'other' => filled($options['salary'] ?? null) ? (float) $options['salary'] : null,
            default => null,
        };

        if ($type === CertificateType::ActiveEmployment && in_array($options['salary_mode'] ?? null, ['basic', 'average', 'other'], true) && ! $options['salary']) {
            throw new BusinessRuleException(match ($options['salary_mode']) {
                'basic' => 'El trabajador no tiene salario básico en la ficha.',
                'average' => 'El trabajador todavía no tiene nóminas cerradas para promediar.',
                default => 'Escriba el salario que debe decir la certificación.',
            });
        }

        $options['delivered'] = array_values(array_intersect(array_keys(self::DELIVERED_DOCUMENTS), (array) ($options['delivered'] ?? [])));

        if (! $options['start_date']) {
            throw new BusinessRuleException('Falta la fecha de ingreso: cárguela en la ficha o escríbala aquí.');
        }

        if ($type->isForExit() && ! $options['end_date']) {
            throw new BusinessRuleException('Falta la fecha de retiro.');
        }

        if ($options['end_date'] && $options['end_date']->lt($options['start_date'])) {
            throw new BusinessRuleException('La fecha de retiro no puede ser anterior a la de ingreso.');
        }

        return $options;
    }
}
