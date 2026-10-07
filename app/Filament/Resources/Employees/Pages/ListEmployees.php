<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\Reports\Excel\FormatoHorasExtrasExcel;
use App\Domain\Reports\Excel\PersonalExcelExport;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEmployees extends ListRecords
{
    use HasBackAction;

    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getHistoryBackAction(),
            $this->exportarExcelAction(),
            $this->formatoHorasAction(),
            CreateAction::make(),
        ];
    }

    /**
     * El Excel de lo que la tabla muestra ahora mismo: la ficha completa, el salario y las
     * horas del mes elegido.
     *
     * Sale de `getFilteredTableQuery()`, así que el filtro de estado, el de área y la
     * búsqueda se respetan.
     */
    private function exportarExcelAction(): Action
    {
        return Action::make('exportarExcel')
            ->label('Exportar a Excel')
            ->tooltip('El personal que muestra la tabla, con su ficha, salario y horas del mes')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->modalHeading('Exportar personal a Excel')
            ->modalDescription('Sale con los filtros que tiene la tabla ahora mismo. Elija el mes de las horas.')
            ->modalSubmitActionLabel('Descargar Excel')
            ->schema([
                Select::make('mes')
                    ->label('Horas del mes')
                    ->options(self::lastTwelveMonths())
                    ->default(now()->format('Y-m'))
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, PersonalExcelExport $export): ?StreamedResponse {
                $query = $this->getFilteredTableQuery();

                if ($query === null) {
                    return null;
                }

                return $export->download(
                    $query,
                    Carbon::createFromFormat('Y-m-d', $data['mes'].'-01'),
                    auth()->user()?->can('viewAnySalary', Employee::class) ?? false,
                );
            });
    }

    /**
     * El formato de horas extras (TH-FOR-002) de todos los que marcaron en el periodo, en
     * un Excel con una hoja por trabajador, como el libro que talento humano llenaba a
     * mano. Respeta los filtros y la búsqueda de la tabla.
     */
    private function formatoHorasAction(): Action
    {
        $periodos = fn (): array => PayrollRun::recentHoursWindows(
            (int) app(PayrollParameterService::class)->valueOrDefault(PayrollParameter::HoursCutoffDay, CarbonImmutable::today(), Filament::getTenant()?->getKey() ?? ''),
        );

        return Action::make('formatoHoras')
            ->label('Formato de horas')
            ->tooltip('El formato de horas extras de todos los que marcaron, una hoja por trabajador')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->can('viewAny', AttendanceDay::class) ?? false)
            ->modalHeading('Formato de horas extras')
            ->modalDescription('Un Excel con una hoja por trabajador que marcó en el periodo, con los filtros que tiene la tabla.')
            ->modalSubmitActionLabel('Descargar Excel')
            ->schema([
                Select::make('periodo')
                    ->label('Periodo')
                    ->options($periodos)
                    ->default(fn (): ?string => array_key_first($periodos()))
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, FormatoHorasExtrasExcel $excel): ?StreamedResponse {
                $query = $this->getFilteredTableQuery();

                if ($query === null) {
                    return null;
                }

                [$from, $to] = array_map(fn (string $d): CarbonImmutable => CarbonImmutable::parse($d), explode('|', $data['periodo']));
                $employees = $query
                    ->whereHas('attendanceDays', fn ($days) => $days->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]))
                    ->orderBy('first_name')
                    ->get();

                return $excel->download($employees, $from, $to, auth()->user()?->can('viewAnySalary', Employee::class) ?? false);
            });
    }

    /** @return array<string, string> «2026-09» => «Septiembre de 2026», del actual hacia atrás. */
    private static function lastTwelveMonths(): array
    {
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $month = now()->startOfMonth()->subMonths($i);
            $months[$month->format('Y-m')] = ucfirst($month->translatedFormat('F \d\e Y'));
        }

        return $months;
    }
}
