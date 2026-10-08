<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\MealAllowanceCalculator;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollRun;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * El auxilio de alimentación del periodo: cuántas comidas ganó cada trabajador por estar
 * trabajando en sus franjas, y cuánto se le paga. Se paga aparte de la nómina, así que
 * esta pantalla es la lista para pagarlo, con su Excel.
 *
 * Solo cuentan los días confirmados en «Horas por confirmar», del 27 al 26.
 */
class Alimentacion extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano';

    protected static ?string $navigationLabel = 'Alimentación';

    protected static ?string $title = 'Auxilio de alimentación';

    protected static ?int $navigationSort = 23;

    protected static ?string $slug = 'alimentacion';

    protected string $view = 'filament.pages.alimentacion';

    /** @var array<string, array<string, array<string, mixed>>> comidas por ventana */
    private array $comidas = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', AttendanceDay::class) ?? false;
    }

    public function table(Table $table): Table
    {
        $columnas = [];

        foreach (MealAllowanceCalculator::MEALS as $comida => $etiqueta) {
            $columnas[] = TextColumn::make("comida_{$comida}")
                ->label($etiqueta)
                ->getStateUsing(fn (Employee $record): ?int => ($n = $this->mealsOf($record)['counts'][$comida] ?? 0) > 0 ? $n : null)
                ->placeholder('—')
                ->alignEnd();
        }

        return $table
            ->query(Employee::query()->active())
            ->modifyQueryUsing(function (Builder $query): Builder {
                [$from, $to] = $this->window();

                // Solo quien tiene días confirmados en el periodo puede haber ganado comidas.
                return $query->whereHas('attendanceDays', fn (Builder $days) => $days
                    ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
                    ->confirmed());
            })
            ->defaultSort('first_name')
            ->description(fn (): string => $this->summary())
            ->emptyStateHeading('Sin comidas en el periodo')
            ->emptyStateDescription('Solo cuentan los días confirmados en «Horas por confirmar».')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Trabajador')
                    ->formatStateUsing(fn (Employee $record): string => $record->fullName())
                    ->description(fn (Employee $record): ?string => $record->document_number)
                    ->searchable(['first_name', 'last_name', 'document_number'])
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Cargo')
                    ->limit(28)
                    ->tooltip(fn (Employee $record): ?string => $record->position)
                    ->placeholder('—')
                    ->toggleable(),
                ...$columnas,
                TextColumn::make('total_comidas')
                    ->label('Comidas')
                    ->getStateUsing(fn (Employee $record): int => $this->mealsOf($record)['meals'] ?? 0)
                    ->tooltip(fn (Employee $record): ?string => collect($this->mealsOf($record)['days'] ?? [])
                        ->map(fn (array $comidas, string $fecha): string => CarbonImmutable::parse($fecha)->format('d/m').': '.implode(', ', $comidas))
                        ->implode(' · ') ?: null)
                    ->alignEnd(),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->getStateUsing(fn (Employee $record): float => $this->mealsOf($record)['amount'] ?? 0)
                    ->money('COP', 0)
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options(fn (): array => $this->windowOptions())
                    ->default(fn (): ?string => array_key_first($this->windowOptions()))
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query): Builder => $query),
            ])
            ->headerActions([
                Action::make('excelPago')
                    ->label('Excel para pagar')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(function (): ?StreamedResponse {
                        $query = $this->getFilteredSortedTableQuery();

                        return $query ? $this->excel($query->get()) : null;
                    }),
            ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} la ventana elegida en el filtro */
    public function window(): array
    {
        $value = $this->getTableFilterState('periodo')['value'] ?? null;
        $value = is_string($value) && str_contains($value, '|') ? $value : array_key_first($this->windowOptions());
        [$from, $to] = explode('|', $value);

        return [CarbonImmutable::parse($from), CarbonImmutable::parse($to)];
    }

    /** @param  Collection<int, Employee>  $employees */
    private function excel($employees): StreamedResponse
    {
        [$from, $to] = $this->window();

        $rows = $employees->map(function (Employee $employee): array {
            $comidas = $this->mealsOf($employee);
            $fila = [
                'Código' => $employee->employee_code,
                'Cédula' => $employee->document_number,
                'Trabajador' => $employee->fullName(),
                'Cargo' => $employee->position,
            ];

            foreach (MealAllowanceCalculator::MEALS as $comida => $etiqueta) {
                $fila[$etiqueta] = $comidas['counts'][$comida] ?? 0;
            }

            return $fila + ['Total comidas' => $comidas['meals'] ?? 0, 'Valor a pagar' => $comidas['amount'] ?? 0];
        });

        return (new FastExcel($rows))->download('ALIMENTACION-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx');
    }

    /** @return array<string, string> */
    private function windowOptions(): array
    {
        return PayrollRun::recentHoursWindows(
            (int) app(PayrollParameterService::class)->valueOrDefault(PayrollParameter::HoursCutoffDay, CarbonImmutable::today(), Filament::getTenant()?->getKey() ?? ''),
        );
    }

    /** @return array<string, mixed> las comidas del trabajador en la ventana elegida */
    private function mealsOf(Employee $employee): array
    {
        return $this->allMeals()[$employee->getKey()] ?? [];
    }

    /** @return array<string, array<string, mixed>> */
    private function allMeals(): array
    {
        [$from, $to] = $this->window();
        $key = $from->toDateString().'|'.$to->toDateString();

        return $this->comidas[$key] ??= app(MealAllowanceCalculator::class)->forEmployees(
            Employee::query()->active()->pluck('id')->all(),
            (string) Filament::getTenant()?->getKey(),
            $from,
            $to,
        );
    }

    /** El total del periodo, en la descripción de la tabla. */
    private function summary(): string
    {
        [$from, $to] = $this->window();
        $todas = collect($this->allMeals());

        return 'Días confirmados del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y').'. '
            .number_format($todas->sum('meals'), 0, ',', '.').' comidas por $ '
            .number_format($todas->sum('amount'), 0, ',', '.').'. Se paga aparte de la nómina.';
    }
}
