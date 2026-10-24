<?php

namespace App\Domain\Reports\Services;

use App\Domain\HumanResources\Enums\CertificateType;
use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\HumanResources\Support\SpanishWords as W;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Los certificados de talento humano hechos con sus propios formatos de Word: el sistema
 * abre la plantilla de `resources/templates/certificados` —el archivo de la extractora, con
 * {{campos}} en los datos de cada persona— y solo cambia esos datos y la firma. El resto
 * del documento —logo, encabezado, tablas, textos, márgenes— es el de la empresa, tal cual.
 *
 * Los moldes se rehacen desde los Word originales con el script que los convierte (marca
 * cada dato por su posición y comprueba el texto que tenía), si la empresa cambia un formato.
 *
 * Los firma quien la empresa tenga puesto para expedirlos —un trabajador de Personal, con
 * su firma escaneada—, y cada uno que se expide queda en la carpeta del trabajador.
 */
class CertificateWordService
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /** La imagen de la firma dentro de las plantillas que la llevan. */
    private const SIGNATURE_MEDIA = 'word/media/image1.png';

    /** Los días hábiles que tiene quien se retira para el examen de egreso. */
    public const EXIT_EXAM_BUSINESS_DAYS = 5;

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

    /** La razón social de cada fondo de cesantías, a quien va dirigida la autorización. */
    public const SEVERANCE_FUND_NAMES = [
        'PORVENIR' => 'Sociedad Administradora de Fondos de Pensiones y Cesantías Porvenir S.A.',
        'PROTECCIÓN' => 'Administradora de Fondos de Pensiones y Cesantías Protección S.A.',
        'PROTECCION' => 'Administradora de Fondos de Pensiones y Cesantías Protección S.A.',
        'COLFONDOS' => 'Colfondos S.A. Pensiones y Cesantías',
        'SKANDIA' => 'Skandia Administradora de Fondos de Pensiones y Cesantías S.A.',
        'FONDO NACIONAL DEL AHORRO' => 'Fondo Nacional del Ahorro',
    ];

    /** Lo que el trabajador declara recibido en el paz y salvo, como en el formato. */
    public const DELIVERED_DOCUMENTS = [
        'certificacion' => 'Certificación laboral',
        'examen' => 'Autorización para el examen médico de egreso',
        'cesantias' => 'Autorización de retiro de cesantías',
        'liquidacion' => 'Liquidación de prestaciones sociales',
    ];

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

    /** El Word del certificado: la plantilla de la empresa con los datos de la persona. */
    public function render(CertificateType $type, Employee $employee, Tenant $tenant, array $options): string
    {
        $values = $this->values($type, $employee, $tenant, $options);
        $template = resource_path('templates/certificados/'.$type->code().'.docx');
        $work = tempnam(sys_get_temp_dir(), 'cert');

        if (! copy($template, $work)) {
            throw new RuntimeException("No se encontró la plantilla {$type->code()}.");
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($work) !== true) {
                throw new RuntimeException("La plantilla {$type->code()} no es un Word válido.");
            }

            $xml = (string) $zip->getFromName('word/document.xml');

            // Sin salario, la fila «Salario mensual» de la tabla sale entera.
            if ($type === CertificateType::ActiveEmployment && $values['salario_cifra'] === null) {
                $xml = (string) preg_replace('/<w:tr\b(?:(?!<\/w:tr>).)*\{\{salario_letras\}\}.*?<\/w:tr>/s', '', $xml, 1);
            }

            $signature = $this->signaturePng($tenant);

            if ($signature && $zip->locateName(self::SIGNATURE_MEDIA) !== false && $type !== CertificateType::Clearance) {
                $xml = $this->fitSignature($xml, (string) $zip->getFromName('word/_rels/document.xml.rels'), $signature);
                $zip->addFromString(self::SIGNATURE_MEDIA, $signature);
            }

            $xml = preg_replace_callback('/\{\{([a-z_]+)\}\}/', function (array $match) use ($values, $type): string {
                if (! array_key_exists($match[1], $values)) {
                    throw new RuntimeException("La plantilla {$type->code()} pide «{$match[1]}» y no se tiene.");
                }

                return htmlspecialchars((string) $values[$match[1]], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            }, $xml);

            $zip->addFromString('word/document.xml', (string) $xml);
            $zip->close();

            return (string) file_get_contents($work);
        } finally {
            @unlink($work);
        }
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

        $document = $this->render($type, $employee, $tenant, $options);
        $path = 'employee-documents/'.$employee->tenant_id.'/'.$employee->id.'/certificados/'.Str::uuid7().'.docx';
        Storage::disk(private_files_disk())->put($path, $document);

        $issuedOn = CarbonImmutable::parse($options['issued_on'] ?? now($tenant->plantTimezone())->toDateString());

        return EmployeeDocument::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'document_type' => EmployeeDocumentType::Certificado,
            'title' => $type->code().' · '.$type->label().' · '.$issuedOn->format('d/m/Y'),
            'file_path' => $path,
            'file_name' => $this->filename($type, $employee),
            'file_size' => strlen($document),
            'mime_type' => self::MIME,
            'notes' => 'Expedido por '.$signer->fullName().($signer->position ? ' ('.$signer->position.')' : '').'.',
            'uploaded_by' => $by->id,
        ]);
    }

    /** «TH-EGR-F-001-068. Certificación laboral Carlos Enrique Arévalo Cano.docx», como los nombra talento humano. */
    public function filename(CertificateType $type, Employee $employee): string
    {
        $code = $type->code().($employee->employee_code ? '-'.$employee->employee_code : '');

        return str_replace(['/', '\\'], '-', $code.'. '.$type->fileLabel().' '.$employee->fullName()).'.docx';
    }

    /** Quien expide: el trabajador que la empresa tiene puesto, si sigue en Personal. */
    public function signer(Tenant $tenant): ?Employee
    {
        $id = $tenant->certificateSettings()['signer_employee_id'];

        return $id ? Employee::query()->forTenant($tenant->id)->whereKey($id)->first() : null;
    }

    /** La firma escaneada de quien expide, para verla en la pantalla. */
    public function signatureBase64(Tenant $tenant): ?string
    {
        $png = $this->signaturePng($tenant);

        return $png ? 'data:image/png;base64,'.base64_encode($png) : null;
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

    /**
     * Los datos de cada campo de las plantillas, escritos como los escribe cada formato: la
     * de persona activa pone el día sin cero («dos (2)»), las del egreso con cero («seis (06)»).
     *
     * @return array<string, string|null>
     */
    public function values(CertificateType $type, Employee $employee, Tenant $tenant, array $options): array
    {
        $o = $this->normalize($type, $employee, $options);
        $signer = $this->signer($tenant);
        $padded = $type !== CertificateType::ActiveEmployment;
        $check = fn (bool $on): string => $on ? '☒' : '☐';
        $cc = fn (?string $number): string => ctype_digit((string) $number) ? number_format((int) $number, 0, ',', '.') : (string) $number;
        $start = $o['start_date'];
        $end = $o['end_date'];
        $issued = $o['issued_on'];
        $contract = $employee->contract_type;

        return [
            'nombre' => $employee->fullName(),
            'cedula' => $cc($employee->document_number),
            'expedida_en' => $employee->document_issue_place ?: '________________',
            'cargo' => $employee->position ?: '________________',
            'tratamiento' => match ($employee->sex) {
                'F' => 'la señora', 'M' => 'el señor', default => 'el (la) señor(a)'
            },
            'del_tratamiento' => match ($employee->sex) {
                'F' => 'de la señora', 'M' => 'del señor', default => 'del (la) señor(a)'
            },
            'genero' => match ($employee->sex) {
                'F' => 'a', 'M' => 'o', default => 'o(a)'
            },
            'firmante' => $signer?->fullName() ?? '',
            'firmante_cargo' => $signer?->position ?? '',
            'firmante_cc' => $signer ? $cc($signer->document_number) : '',
            'x_indefinido' => $check($contract === 'indefinido'),
            'x_fijo' => $check($contract === 'fijo'),
            'x_obra' => $check($contract === 'obra_labor'),
            'x_aprendizaje' => $check($contract === 'aprendizaje'),
            'salario_letras' => $o['salary'] ? W::capitalize(W::amount($o['salary'])) : null,
            'salario_cifra' => $o['salary'] ? W::figure($o['salary']) : null,
            'salario_nota' => filled($o['salary_note'] ?? null) ? ' '.trim((string) $o['salary_note']) : '',
            'fecha_ingreso' => W::date($start, $padded),
            'fecha_retiro' => $end ? W::date($end, $padded) : '',
            'fecha_retiro_corta' => $end?->format('d/m/Y') ?? '',
            'fecha_expedicion' => $issued->format('d/m/Y'),
            'fecha_expedicion_dias' => W::daysOfMonth($issued, $padded),
            'ingreso_dia' => W::dayWords($start),
            'ingreso_dd' => $start->format('d'),
            'ingreso_mes' => W::month($start),
            'ingreso_anio_letras' => W::number($start->year),
            'ingreso_anio' => (string) $start->year,
            'retiro_dia' => $end ? W::dayWords($end) : '',
            'retiro_dd' => $end?->format('d') ?? '',
            'retiro_mes' => $end ? W::month($end) : '',
            'retiro_anio_letras' => $end ? W::number($end->year) : '',
            'retiro_anio' => $end ? (string) $end->year : '',
            'expedicion_dia' => W::day($issued),
            'expedicion_mes' => W::month($issued),
            'expedicion_anio_letras' => W::number($issued->year),
            'expedicion_anio' => (string) $issued->year,
            'fondo' => ($o['fund_name'] ?? null) ?: '________________',
            'x_certificacion' => $check(in_array('certificacion', $o['delivered'], true)),
            'x_examen' => $check(in_array('examen', $o['delivered'], true)),
            'x_cesantias' => $check(in_array('cesantias', $o['delivered'], true)),
            'x_liquidacion' => $check(in_array('liquidacion', $o['delivered'], true)),
            'x_otro' => $check(filled($o['delivered_other'] ?? null)),
            'otro' => filled($o['delivered_other'] ?? null) ? trim((string) $o['delivered_other']) : '____________________________________________',
            'ips' => ($o['exit_exam_provider'] ?? null) ?: '________________',
            'plazo_examen' => $o['exit_exam_deadline']?->format('d/m/Y') ?? '',
        ];
    }

    /**
     * Las fechas como fechas, el salario según el modo elegido y lo que falta para el tipo,
     * comprobado: los de egreso necesitan la fecha de retiro.
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
        $options['delivered'] = array_values(array_intersect(array_keys(self::DELIVERED_DOCUMENTS), (array) ($options['delivered'] ?? [])));

        $mode = $type === CertificateType::ActiveEmployment ? ($options['salary_mode'] ?? 'basic') : 'none';
        $options['salary'] = match ($mode) {
            'basic' => (float) $employee->base_salary > 0 ? (float) $employee->base_salary : null,
            'average' => $this->averageSalary($employee)['amount'] ?? null,
            'other' => filled($options['salary'] ?? null) ? (float) $options['salary'] : null,
            default => null,
        };

        if ($mode !== 'none' && ! $options['salary']) {
            throw new BusinessRuleException(match ($mode) {
                'basic' => 'El trabajador no tiene salario básico en la ficha.',
                'average' => 'El trabajador todavía no tiene nóminas cerradas para promediar.',
                default => 'Escriba el salario que debe decir la certificación.',
            });
        }

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

    /** La firma de quien expide en PNG, como la pide la plantilla (la de Word es PNG). */
    private function signaturePng(Tenant $tenant): ?string
    {
        $path = $tenant->certificateSettings()['signature_path'];
        $disk = Storage::disk(private_files_disk());

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $bytes = (string) $disk->get($path);

        if (str_starts_with($bytes, "\x89PNG")) {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Ajusta el tamaño del cuadro de la firma a las proporciones de la nueva: misma altura
     * que la de la plantilla, sin pasarse de su ancho, para que no salga estirada.
     */
    private function fitSignature(string $xml, string $rels, string $png): string
    {
        $size = @getimagesizefromstring($png);

        if (! $size || ! preg_match('/Id="(rId\d+)"[^>]*Target="media\/image1\.png"/', $rels, $rel)) {
            return $xml;
        }

        [$width, $height] = $size;

        return (string) preg_replace_callback('/<w:drawing>(?:(?!<\/w:drawing>).)*r:embed="'.$rel[1].'".*?<\/w:drawing>/s', function (array $drawing) use ($width, $height): string {
            if (! preg_match('/<wp:extent cx="(\d+)" cy="(\d+)"/', $drawing[0], $extent)) {
                return $drawing[0];
            }

            $maxWidth = (int) $extent[1];
            $boxHeight = (int) $extent[2];
            $cx = (int) round($boxHeight * $width / max(1, $height));
            $cy = $boxHeight;

            if ($cx > $maxWidth) {
                $cx = $maxWidth;
                $cy = (int) round($maxWidth * $height / max(1, $width));
            }

            return (string) preg_replace(
                ['/(<wp:extent) cx="\d+" cy="\d+"/', '/(<a:ext) cx="\d+" cy="\d+"/'],
                ['$1 cx="'.$cx.'" cy="'.$cy.'"', '$1 cx="'.$cx.'" cy="'.$cy.'"'],
                $drawing[0],
            );
        }, $xml, 1);
    }
}
