<?php

namespace App\Filament\Resources\Employees\Widgets;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Support\EmployeeProfileOptions as Options;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use App\Models\Tenant;
use App\Support\Hours;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * La cabecera de la ficha: quién es, en qué está y qué le falta, de un vistazo.
 *
 * Nombre, cargo, área y estado; y en tarjetas el ingreso con su antigüedad, el contrato
 * (con aviso si el fijo está por vencer), el carné, el salario —solo para quien ve
 * sueldos—, la carpeta de documentos y cómo va en el corte de horas.
 */
class EmployeeProfileCard extends Widget
{
    protected string $view = 'filament.resources.employees.widgets.employee-profile-card';

    protected int|string|array $columnSpan = 'full';

    /** Va arriba de todo: se pinta con la página, sin esperar. */
    protected static bool $isLazy = false;

    public ?Model $record = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Employee $employee */
        $employee = $this->record;
        $employee->loadMissing(['documents', 'qrCode']);
        /** @var Tenant|null $tenant */
        $tenant = Filament::getTenant();
        $today = CarbonImmutable::now($tenant?->plantTimezone() ?? 'America/Bogota')->startOfDay();

        $tiles = [];

        // Ingreso y antigüedad.
        if ($employee->hire_date) {
            $tiles[] = [
                'label' => 'Ingreso',
                'value' => $employee->hire_date->format('d/m/Y'),
                'detail' => self::seniority(CarbonImmutable::instance($employee->hire_date), $today),
                'tone' => 'neutral',
                'icon' => 'heroicon-o-calendar',
            ];
        }

        // Contrato, con el aviso del fijo.
        $contract = Options::CONTRACT_TYPES[$employee->contract_type] ?? ($employee->contract_type ?: 'Sin contrato');
        $end = $employee->contract_end_date ? CarbonImmutable::instance($employee->contract_end_date) : null;
        $tiles[] = [
            'label' => 'Contrato',
            'value' => $contract,
            'detail' => $end ? ($end->lt($today) ? 'Venció el '.$end->format('d/m/Y') : 'Termina el '.$end->format('d/m/Y').' ('.(int) $today->diffInDays($end).' días)') : null,
            'tone' => $end && $end->lt($today) ? 'danger' : ($employee->contractEndsWithin(30) ? 'warning' : 'neutral'),
            'icon' => 'heroicon-o-document-text',
        ];

        // Carné QR.
        $tiles[] = [
            'label' => 'Carné QR',
            'value' => $employee->qrCode ? 'Vigente' : 'Sin carné',
            'detail' => $employee->qrCode ? 'Puede marcar en portería' : 'No puede marcar en portería',
            'tone' => $employee->qrCode ? 'ok' : 'warning',
            'icon' => 'heroicon-o-qr-code',
        ];

        // Salario, solo para quien ve sueldos.
        if (auth()->user()?->can('viewSalary', $employee) ?? false) {
            $salary = (float) $employee->base_salary;
            $tiles[] = [
                'label' => 'Salario',
                'value' => $salary > 0 ? '$ '.number_format($salary, 0, ',', '.') : 'Sin salario',
                'detail' => $salary > 0 ? ($employee->salary_type === 'integral' ? 'Integral' : 'Básico mensual') : 'No se puede liquidar',
                'tone' => $salary > 0 ? 'neutral' : 'danger',
                'icon' => 'heroicon-o-banknotes',
            ];
        }

        // Carpeta de documentos.
        $missing = count($employee->missingRequiredDocuments());
        $tiles[] = [
            'label' => 'Documentos',
            'value' => $employee->requiredDocumentsProgress(),
            'detail' => $missing === 0 ? 'Carpeta completa' : "Faltan {$missing}",
            'tone' => $missing === 0 ? 'ok' : 'warning',
            'icon' => 'heroicon-o-folder',
        ];

        // Cómo va en el mes.
        $month = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->between($today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString())
            ->get();
        $tiles[] = [
            'label' => 'Este mes',
            'value' => $month->count().' '.($month->count() === 1 ? 'día' : 'días'),
            'detail' => ($overtime = Hours::format($month->sum(fn (AttendanceDay $d): float => $d->hours()->overtimeHours()))) !== ''
                ? $overtime.' de horas extras'
                : 'Sin horas extras',
            'tone' => 'neutral',
            'icon' => 'heroicon-o-clock',
        ];

        // La novedad de hoy, si la hay: vacaciones, incapacidad, permiso.
        $novelty = EmployeeNovelty::query()
            ->where('employee_id', $employee->id)
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->first();

        return [
            'employee' => $employee,
            'initials' => collect([$employee->first_name, $employee->last_name])
                ->filter()
                ->map(fn (string $part): string => mb_strtoupper(mb_substr(trim($part), 0, 1)))
                ->implode(''),
            'area' => collect([Options::AREAS[$employee->area] ?? $employee->area, Options::SPECIFIC_AREAS[$employee->area_specific] ?? $employee->area_specific])->filter()->unique()->implode(' · '),
            'status' => $employee->status ?? EmploymentStatus::Activo,
            'tiles' => $tiles,
            'novelty' => $novelty ? $novelty->type->label().' hasta el '.$novelty->ends_on->format('d/m/Y') : null,
        ];
    }

    /** «2 años y 3 meses», «5 meses», «12 días». */
    public static function seniority(CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($from->gt($to)) {
            return 'Empieza el '.$from->format('d/m/Y');
        }

        $diff = $from->diff($to);
        $parts = array_filter([
            $diff->y > 0 ? $diff->y.' '.($diff->y === 1 ? 'año' : 'años') : null,
            $diff->m > 0 ? $diff->m.' '.($diff->m === 1 ? 'mes' : 'meses') : null,
        ]);

        return $parts === [] ? $diff->d.' '.($diff->d === 1 ? 'día' : 'días') : implode(' y ', $parts);
    }
}
