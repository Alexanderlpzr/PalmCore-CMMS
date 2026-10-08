<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\OvertimeSummary;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\Reports\Excel\FormatoHorasExtrasExcel;
use App\Domain\Reports\Services\FormatoHorasExtrasPdfService as Formato;
use App\Filament\Resources\Employees\EmployeeResource;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * El formato de horas extras (TH-FOR-002) de todos los trabajadores, en un solo lugar: una
 * tabla con lo que cada uno lleva en el corte —extras, recargos, bonificación, días por
 * confirmar—, buscable, y desde cada fila su formato en pantalla, en PDF o en Excel.
 * Arriba, el Excel de todos con una hoja por trabajador, como el libro TH-NOM-P-001.
 *
 * La ve quien ve las horas de la puerta, como «Horas por confirmar». Los valores en pesos
 * solo salen en las descargas, y solo para quien ve sueldos.
 */
class HorasExtras extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano';

    protected static ?string $navigationLabel = 'Horas extras';

    protected static ?string $title = 'Horas extras';

    /** Justo después de «Horas por confirmar»: primero se confirma, después se mira. */
    protected static ?int $navigationSort = 22;

    protected static ?string $slug = 'horas-extras';

    protected string $view = 'filament.pages.horas-extras';

    /** @var array<string, array<string, array<string, mixed>>> totales por ventana */
    private array $totales = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', AttendanceDay::class) ?? false;
    }

    public function table(Table $table): Table
    {
        $horas = fn (string $campo): \Closure => fn (Employee $record): ?float => ($v = $this->totalsOf($record)[$campo] ?? 0) > 0 ? $v : null;
        $detalle = fn (array $bolsas, string $parte = 'legal'): \Closure => fn (Employee $record): ?string => collect($bolsas)
            ->map(fn (string $bolsa): ?string => ($h = $this->totalsOf($record)[$parte][$bolsa] ?? 0) > 0 ? Formato::COLUMNS[$bolsa].' '.$this->hours($h) : null)
            ->filter()
            ->implode(' · ') ?: null;

        return $table
            ->query(Employee::query()->active())
            ->defaultSort('first_name')
            ->description(fn (): string => 'Horas del '.$this->window()[0]->format('d/m/Y').' al '.$this->window()[1]->format('d/m/Y').'. Extras y recargos como horas extras; lo que pasa del tope diario y los días del mes anterior, como bonificación.')
            ->emptyStateHeading('Nadie marcó en este periodo')
            ->emptyStateDescription('Cambie el periodo o quite el filtro «Solo los que marcaron».')
            ->columns([
                TextColumn::make('employee_code')
                    ->label('Código')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
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
                TextColumn::make('immediate_supervisor')
                    ->label('Jefe inmediato')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dias')
                    ->label('Días')
                    ->getStateUsing(fn (Employee $record): int => $this->totalsOf($record)['days'] ?? 0)
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('extras')
                    ->label('Extras')
                    ->getStateUsing($horas('overtime'))
                    ->tooltip($detalle(['overtime_day', 'overtime_night', 'overtime_sunday_day', 'overtime_sunday_night']))
                    ->numeric(2)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('recargos')
                    ->label('Recargos')
                    ->getStateUsing($horas('surcharges'))
                    ->tooltip($detalle(OvertimeSummary::SURCHARGES))
                    ->numeric(2)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('bono')
                    ->label('Bonificación')
                    ->getStateUsing($horas('bonusHours'))
                    ->tooltip($detalle(array_keys(Formato::COLUMNS), 'bonus'))
                    ->numeric(2)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('sin_confirmar')
                    ->label('Por confirmar')
                    ->getStateUsing(fn (Employee $record): ?int => ($v = $this->totalsOf($record)['unconfirmed'] ?? 0) > 0 ? $v : null)
                    ->color('warning')
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options(fn (): array => $this->windowOptions())
                    ->default(fn (): ?string => array_key_first($this->windowOptions()))
                    ->selectablePlaceholder(false)
                    // Solo elige la ventana: qué filas salen lo decide el filtro de abajo.
                    ->query(fn (Builder $query): Builder => $query),
                Filter::make('solo_marcaron')
                    ->label('Solo los que marcaron')
                    ->toggle()
                    ->default()
                    ->query(function (Builder $query): Builder {
                        [$from, $to] = $this->window();

                        return $query->whereHas('attendanceDays', fn (Builder $days) => $days->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]));
                    }),
            ])
            ->recordActions([
                // Sus días del periodo, para ajustar horas o anular un día sin salir de aquí.
                Action::make('dias')
                    ->label('Días')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->modalHeading(fn (Employee $record): string => 'Días de '.$record->fullName())
                    ->modalContent(fn (Employee $record) => view('filament.pages.partials.dias-del-trabajador', ['employee' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl'),
                Action::make('verFormato')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->tooltip('El formato en la ficha del trabajador')
                    ->url(fn (Employee $record): string => EmployeeResource::getUrl('edit', ['record' => $record, 'relation' => 1])),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->action(function (Employee $record): StreamedResponse {
                        [$from, $to] = $this->window();
                        $service = app(Formato::class);

                        return response()->streamDownload(
                            fn () => print $service->generate($record, $from, $to, auth()->user()?->can('viewSalary', $record) ?? false),
                            $service->filename($record, $to),
                        );
                    }),
                Action::make('excel')
                    ->label('Excel')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->color('gray')
                    ->action(function (Employee $record): StreamedResponse {
                        [$from, $to] = $this->window();

                        return app(FormatoHorasExtrasExcel::class)->download(collect([$record]), $from, $to, auth()->user()?->can('viewSalary', $record) ?? false);
                    }),
            ])
            ->headerActions([
                // Todos los de la tabla —con su búsqueda y sus filtros—, una hoja por trabajador.
                Action::make('excelTodos')
                    ->label('Excel de todos')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(function (): ?StreamedResponse {
                        [$from, $to] = $this->window();
                        $query = $this->getFilteredSortedTableQuery();

                        return $query
                            ? app(FormatoHorasExtrasExcel::class)->download($query->get(), $from, $to, auth()->user()?->can('viewAnySalary', Employee::class) ?? false)
                            : null;
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

    /** @return array<string, string> las últimas ventanas de corte, la que corre primero */
    private function windowOptions(): array
    {
        return PayrollRun::recentHoursWindows(
            (int) app(PayrollParameterService::class)->valueOrDefault(PayrollParameter::HoursCutoffDay, CarbonImmutable::today(), Filament::getTenant()?->getKey() ?? ''),
        );
    }

    /** @return array<string, mixed> los totales del trabajador en la ventana elegida */
    private function totalsOf(Employee $employee): array
    {
        [$from, $to] = $this->window();
        $key = $from->toDateString().'|'.$to->toDateString();

        $this->totales[$key] ??= app(OvertimeSummary::class)->forEmployees(
            Employee::query()->active()->pluck('id')->all(),
            $employee->tenant_id,
            $from,
            $to,
        );

        return $this->totales[$key][$employee->getKey()] ?? [];
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
