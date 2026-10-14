<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Services\HumanResourcesDashboard;
use App\Filament\Resources\AttendanceScans\AttendanceScanResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Filament\Widgets\HumanResources\NoveltiesByTypeChart;
use App\Filament\Widgets\HumanResources\OvertimeByGroupChart;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\Tenant;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * El tablero de Talento Humano: lo primero que ve la cuenta de RRHH al entrar.
 *
 * Arriba, lo que requiere atención hoy —cada número lleva a la lista ya filtrada donde se
 * resuelve—; después el corte de horas en curso, los accesos a lo que se hace a diario y
 * dos gráficos: horas extras por grupo en los últimos cortes y días de novedad del corte.
 */
class TableroTalentoHumano extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano · Día a día';

    protected static ?string $navigationLabel = 'Tablero';

    protected static ?string $title = 'Talento Humano';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'talento-humano';

    protected string $view = 'filament.pages.tablero-talento-humano';

    /**
     * Para quien administra el personal y corrige sus horas. La portería también ve
     * personal y marcas, pero su pantalla es la puerta: el tablero no es para ella.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('viewAny', Employee::class) ?? false)
            && ($user?->can('correct', AttendanceScan::class) ?? false);
    }

    public function getSubheading(): ?string
    {
        return 'Hoy, '.CarbonImmutable::now($this->tenant()->plantTimezone())->locale('es')->translatedFormat('l j \d\e F \d\e Y').'.';
    }

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [OvertimeByGroupChart::class, NoveltiesByTypeChart::class];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenant = $this->tenant();
        $dashboard = app(HumanResourcesDashboard::class);
        $attention = $dashboard->attention($tenant);
        $cutoff = $dashboard->currentCutoff($tenant);
        $user = auth()->user();
        $today = CarbonImmutable::now($tenant->plantTimezone())->toDateString();
        $canSeeEmployees = $user?->can('viewAny', Employee::class) ?? false;
        $canSeeHours = $user?->can('viewAny', AttendanceDay::class) ?? false;
        $canSeeSalaries = $user?->can('viewAnySalary', Employee::class) ?? false;

        $cards = array_values(array_filter([
            $canSeeHours ? [
                'label' => 'Adentro ahora',
                'value' => $attention['inside'],
                'detail' => $attention['markedToday'].' '.($attention['markedToday'] === 1 ? 'persona marcó' : 'personas marcaron').' hoy',
                'icon' => 'heroicon-o-user-group',
                'tone' => 'neutral',
                'url' => AttendanceScanResource::getUrl('index'),
            ] : null,
            $canSeeHours ? [
                'label' => 'Entradas sin salida',
                'value' => $attention['openEntries'],
                'detail' => 'Turnos de los últimos 30 días sin salida marcada',
                'icon' => 'heroicon-o-arrow-right-end-on-rectangle',
                'tone' => $attention['openEntries'] > 0 ? 'danger' : 'ok',
                'url' => AttendanceScanResource::getUrl('index', ['filters' => ['fecha' => [
                    'desde' => CarbonImmutable::parse($today)->subDays(HumanResourcesDashboard::OPEN_ENTRIES_LOOKBACK_DAYS)->toDateString(),
                    'hasta' => $today,
                    'sin_salida' => true,
                ]]]),
            ] : null,
            $canSeeHours ? [
                'label' => 'Días por confirmar',
                'value' => $attention['toConfirm'],
                'detail' => 'Del corte en curso, hasta hoy',
                'icon' => 'heroicon-o-clipboard-document-check',
                'tone' => $attention['toConfirm'] > 0 ? 'warning' : 'ok',
                'url' => RevisarHoras::getUrl(),
            ] : null,
            $canSeeEmployees ? [
                'label' => 'Contratos por vencer',
                'value' => $attention['contractsEnding'],
                'detail' => 'Término fijo, en los próximos 30 días',
                'icon' => 'heroicon-o-document-text',
                'tone' => $attention['contractsEnding'] > 0 ? 'warning' : 'ok',
                'url' => EmployeeResource::getUrl('index', ['filters' => ['contrato_por_vencer' => ['isActive' => true]]]),
            ] : null,
            $canSeeSalaries ? [
                'label' => 'Sin salario',
                'value' => $attention['withoutSalary'],
                'detail' => 'Activos sin salario: no se pueden liquidar',
                'icon' => 'heroicon-o-banknotes',
                'tone' => $attention['withoutSalary'] > 0 ? 'danger' : 'ok',
                'url' => EmployeeResource::getUrl('index', ['filters' => ['sin_salario' => ['isActive' => true]]]),
            ] : null,
            $canSeeEmployees ? [
                'label' => 'Sin carné',
                'value' => $attention['withoutBadge'],
                'detail' => 'Activos sin carné QR vigente',
                'icon' => 'heroicon-o-qr-code',
                'tone' => $attention['withoutBadge'] > 0 ? 'warning' : 'ok',
                'url' => EmployeeResource::getUrl('index', ['filters' => ['sin_carne' => ['isActive' => true]]]),
            ] : null,
        ]));

        $shortcuts = array_values(array_filter([
            $canSeeHours ? ['label' => 'Agregar marca', 'icon' => 'heroicon-o-plus-circle', 'url' => AttendanceScanResource::getUrl('index')] : null,
            $canSeeHours ? ['label' => 'Revisar semana', 'icon' => 'heroicon-o-calendar-days', 'url' => RevisarHoras::getUrl()] : null,
            HorasConfirmadas::canAccess() ? ['label' => 'Horas confirmadas', 'icon' => 'heroicon-o-calendar-days', 'url' => HorasConfirmadas::getUrl()] : null,
            HorasExtras::canAccess() ? ['label' => 'Horas extras', 'icon' => 'heroicon-o-clock', 'url' => HorasExtras::getUrl()] : null,
            PayrollRunResource::canViewAny() ? ['label' => 'Nómina', 'icon' => 'heroicon-o-banknotes', 'url' => PayrollRunResource::getUrl('index')] : null,
            Alimentacion::canAccess() ? ['label' => 'Alimentación', 'icon' => 'heroicon-o-cake', 'url' => Alimentacion::getUrl()] : null,
            FactorDeHoras::canAccess() ? ['label' => 'Factor de horas', 'icon' => 'heroicon-o-chart-bar', 'url' => FactorDeHoras::getUrl()] : null,
            EmployeeResource::canCreate() ? ['label' => 'Nuevo trabajador', 'icon' => 'heroicon-o-user-plus', 'url' => EmployeeResource::getUrl('create')] : null,
        ]));

        return [
            'cards' => $cards,
            'cutoff' => $canSeeHours ? $cutoff : null,
            'shortcuts' => $shortcuts,
        ];
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }
}
