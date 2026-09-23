<?php

namespace App\Domain\Reports\Excel;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Support\EmployeeProfileOptions as Options;
use App\Models\AttendanceDay;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El listado de Personal en Excel: la ficha completa de cada persona y sus horas del mes.
 *
 * Recibe la **consulta ya filtrada** de la tabla, como el PDF de Paradas: quien filtra por
 * «Área: Mantenimiento» se lleva a mantenimiento.
 *
 * Las horas se separan en dos: las **confirmadas**, que son las que se pagan, y las **por
 * confirmar**, que el reloj propuso y nadie ha firmado. Sumarlas juntas daría una cifra
 * que no es ni lo trabajado ni lo pagable.
 *
 * Las cifras van como números y no como texto formateado, para que en Excel se puedan
 * sumar y filtrar. El salario solo va si quien descarga puede verlo: el archivo no puede
 * ser la puerta de atrás del permiso `employee-salaries.view`.
 */
class PersonalExcelExport
{
    /**
     * @param  Builder<Employee>  $query  la consulta de la tabla, con sus filtros
     */
    public function download(Builder $query, CarbonInterface $month, bool $includeSalary): StreamedResponse
    {
        return (new FastExcel($this->rows($query, $month, $includeSalary)))
            ->download($this->filename($month));
    }

    public function filename(CarbonInterface $month): string
    {
        return 'PERSONAL-'.$month->format('Y-m').'.xlsx';
    }

    /**
     * Una fila por trabajador, ya con sus encabezados.
     *
     * @param  Builder<Employee>  $query
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Builder $query, CarbonInterface $month, bool $includeSalary): Collection
    {
        $employees = (clone $query)
            ->with('documents:id,employee_id,document_type')
            ->reorder()
            ->orderBy('position')
            ->orderBy('last_name')
            ->get();

        $hours = $this->hoursByEmployee(
            $employees->modelKeys(),
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        );

        return $employees->map(fn (Employee $employee): array => $this->row(
            $employee,
            $hours[(string) $employee->id] ?? null,
            $includeSalary,
        ));
    }

    /**
     * @param  array{days: int, worked: float, overtime: float, surcharges: float, pending: float}|null  $hours
     * @return array<string, mixed>
     */
    private function row(Employee $employee, ?array $hours, bool $includeSalary): array
    {
        $hours ??= ['days' => 0, 'worked' => 0.0, 'overtime' => 0.0, 'surcharges' => 0.0, 'pending' => 0.0];

        $row = [
            'Cargo' => $employee->position,
            'Código' => $employee->employee_code,
            'Código empresarial' => $employee->company_code,
            'Nombres' => $employee->first_name,
            'Apellidos' => $employee->last_name,
            'Tipo documento' => $employee->document_type,
            'Documento' => $employee->document_number,
            'Estado' => $employee->status?->label(),
            'Área' => Options::AREAS[$employee->area] ?? null,
            'Área específica' => Options::SPECIFIC_AREAS[$employee->area_specific] ?? null,
            'Tipo de contrato' => Options::CONTRACT_TYPES[$employee->contract_type] ?? null,
            'Fecha de ingreso' => $employee->hire_date?->format('Y-m-d'),
            'Fecha de retiro' => $employee->termination_date?->format('Y-m-d'),
        ];

        if ($includeSalary) {
            $row['Salario básico'] = (float) $employee->base_salary;
            $row['Tipo de salario'] = $employee->salary_type === 'integral' ? 'Integral' : 'Ordinario';
        }

        $earnsOvertime = $employee->earnsOvertime();

        return $row + [
            'Causa horas extras' => $earnsOvertime ? 'Sí' : 'No',
            'Días confirmados' => $hours['days'],
            'Horas trabajadas (confirmadas)' => $hours['worked'],
            'Horas extras' => $earnsOvertime ? $hours['overtime'] : 0.0,
            'Horas de recargo' => $earnsOvertime ? $hours['surcharges'] : 0.0,
            'Horas por confirmar' => $hours['pending'],
            'Carpeta' => $employee->requiredDocumentsProgress(),
            'Documentos faltantes' => collect($employee->missingRequiredDocuments())->map->shortLabel()->implode(', '),
            'Celular' => $employee->phone,
            'Correo' => $employee->email,
            'Fecha de nacimiento' => $employee->birth_date?->format('Y-m-d'),
            'Edad' => $employee->birth_date?->age,
            'Sexo' => Options::SEXES[$employee->sex] ?? null,
            'RH' => $employee->blood_type,
            'Alergias' => $employee->allergies,
            'Municipio / vereda' => $employee->city,
            'Dirección' => $employee->address,
            'Tiene hijos' => $employee->has_children === null ? null : ($employee->has_children ? 'Sí' : 'No'),
            'Número de hijos' => $employee->children_count,
            'Fecha de expedición' => $employee->document_issue_date?->format('Y-m-d'),
            'Lugar de expedición' => $employee->document_issue_place,
            'Contacto de emergencia' => $employee->emergency_contact_name,
            'Parentesco' => $employee->emergency_contact_relationship,
            'Teléfono de emergencia' => $employee->emergency_contact_phone,
            'EPS' => $employee->eps,
            'Fondo de pensiones' => $employee->pension_fund,
            'ARL' => $employee->arl,
            'Clase de riesgo ARL' => $employee->arl_risk_class,
            'Fondo de cesantías' => $employee->severance_fund,
            'Caja de compensación' => $employee->compensation_fund,
            'Talla camisa' => $employee->shirt_size,
            'Talla pantalón' => $employee->pants_size,
            'Talla botas' => $employee->boot_size,
            'Talla rompevientos' => $employee->jacket_size,
        ];
    }

    /**
     * Las horas del mes por trabajador, en una sola consulta agrupada.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array{days: int, worked: float, overtime: float, surcharges: float, pending: float}>
     */
    public function hoursByEmployee(array $employeeIds, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $confirmed = AttendanceDayStatus::Confirmada->value;

        $rows = AttendanceDay::withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('employee_id')
            ->selectRaw('employee_id')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as days', [$confirmed])
            ->selectRaw('sum(case when status = ? then worked_hours else 0 end) as worked', [$confirmed])
            ->selectRaw('sum(case when status = ? then overtime_day_hours + overtime_night_hours + overtime_sunday_day_hours + overtime_sunday_night_hours else 0 end) as overtime', [$confirmed])
            ->selectRaw('sum(case when status = ? then night_surcharge_hours + sunday_surcharge_hours + night_sunday_surcharge_hours else 0 end) as surcharges', [$confirmed])
            ->selectRaw('sum(case when status <> ? then worked_hours else 0 end) as pending', [$confirmed])
            ->get();

        $hours = [];

        foreach ($rows as $row) {
            $hours[(string) $row->employee_id] = [
                'days' => (int) $row->days,
                'worked' => round((float) $row->worked, 2),
                'overtime' => round((float) $row->overtime, 2),
                'surcharges' => round((float) $row->surcharges, 2),
                'pending' => round((float) $row->pending, 2),
            ];
        }

        return $hours;
    }
}
