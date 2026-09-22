<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Domain\Reports\Excel\PersonalExcelExport;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
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
