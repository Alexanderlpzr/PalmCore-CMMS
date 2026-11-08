<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Support\DayTone;
use App\Domain\Reports\Excel\HorasConfirmadasExcel;
use App\Filament\Concerns\PeriodoDeCorte;
use App\Filament\Resources\AttendanceDays\AttendanceDayActions;
use App\Filament\Resources\AttendanceDays\EmployeeDetailAction;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Support\Hours;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Los días ya firmados en «Horas por confirmar», para corregirlos cuando llega una novedad
 * tarde: ajustar sus horas o anularlo, con motivo. El día sigue confirmado y queda quién,
 * cuándo y por qué lo cambió. Lo que ya se pagó en una nómina cerrada no se deja tocar.
 */
class HorasConfirmadas extends Page implements HasTable
{
    use InteractsWithTable;
    use PeriodoDeCorte;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano · Día a día';

    protected static ?string $navigationLabel = 'Horas confirmadas';

    protected static ?string $title = 'Horas confirmadas';

    /** Entre «Horas por confirmar» y «Horas extras»: el orden en que se trabajan. */
    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'horas-confirmadas';

    protected string $view = 'filament.pages.horas-confirmadas';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', AttendanceDay::class) ?? false;
    }

    /**
     * Lo de los días que muestra la tabla —con sus filtros—, para las tarjetas de arriba.
     *
     * @return array{days: int, people: int, worked: float, surcharges: float, overtime: float, adjusted: int}
     */
    public function resumen(): array
    {
        $dias = $this->getFilteredTableQuery()?->get() ?? collect();

        return [
            'days' => $dias->count(),
            'people' => $dias->pluck('employee_id')->unique()->count(),
            'worked' => round((float) $dias->sum('worked_hours'), 2),
            'surcharges' => round((float) $dias->sum(fn (AttendanceDay $d): float => (float) $d->night_surcharge_hours + (float) $d->sunday_surcharge_hours + (float) $d->night_sunday_surcharge_hours), 2),
            'overtime' => round((float) $dias->sum(fn (AttendanceDay $d): float => (float) $d->overtime_day_hours + (float) $d->overtime_night_hours + (float) $d->overtime_sunday_day_hours + (float) $d->overtime_sunday_night_hours), 2),
            'adjusted' => $dias->filter(fn (AttendanceDay $d): bool => $d->isManuallyAdjusted())->count(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AttendanceDay::query()->confirmed()->with(['employee', 'confirmedBy:id,name', 'adjustedBy:id,name']))
            ->defaultSort('work_date', 'desc')
            ->emptyStateHeading('Sin días confirmados en el período')
            ->emptyStateDescription('Confirme los días en «Revisar semana» o en «Horas por confirmar», o cambie el período en los filtros.')
            ->columns([
                TextColumn::make('work_date')
                    ->label('Fecha')
                    ->date('D d/m/Y')
                    ->sortable(),
                TextColumn::make('employee.first_name')
                    ->label('Trabajador')
                    ->getStateUsing(fn (AttendanceDay $record): string => $record->employee?->fullName() ?? '—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('employee', fn (Builder $e) => $e
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%"))),
                TextColumn::make('worked_hours')
                    ->label('Trabajadas')
                    ->formatStateUsing(fn ($state): string => Hours::format($state))
                    ->alignEnd()
                    ->description(fn (AttendanceDay $record): ?string => $record->isManuallyAdjusted() ? 'Ajustado a mano' : null)
                    ->tooltip(fn (AttendanceDay $record): ?string => $record->isManuallyAdjusted()
                        ? 'Ajustado por '.($record->adjustedBy?->name ?? '—').' el '.$record->adjusted_at?->format('d/m/Y').': '.$record->adjustment_reason
                        : null),
                TextColumn::make('night_surcharge_hours')->label('Recargo nocturno')->wrapHeader()->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(),
                TextColumn::make('sunday_surcharge_hours')->label('Recargo dominical')->wrapHeader()->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('night_sunday_surcharge_hours')->label('Recargo nocturno dominical')->wrapHeader()->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_day_hours')->label('Extra diurna')->wrapHeader()->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(),
                TextColumn::make('overtime_night_hours')->label('Extra nocturna')->wrapHeader()->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(),
                TextColumn::make('overtime_sunday_day_hours')->label('Extra dominical diurna')->wrapHeader()->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_sunday_night_hours')->label('Extra dominical nocturna')->wrapHeader()->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(fn (AttendanceDay $record): string => DayTone::label($record))
                    ->color(fn (AttendanceDay $record): string => DayTone::color($record)),
                TextColumn::make('confirmedBy.name')
                    ->label('Firmado por')
                    ->description(fn (AttendanceDay $record): ?string => $record->confirmed_at?->format('d/m/Y'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                $this->periodFilter('work_date'),
                SelectFilter::make('employee_id')
                    ->label('Trabajador')
                    ->options(fn (): array => Employee::query()->active()->orderBy('first_name')->get()
                        ->mapWithKeys(fn (Employee $e): array => [$e->id => $e->fullName()])->all())
                    ->searchable(),
            ])
            // Las filas de la pantalla —con su periodo, su búsqueda y sus filtros— en Excel.
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(function (): ?StreamedResponse {
                        $query = $this->getFilteredSortedTableQuery();

                        if (! $query) {
                            return null;
                        }

                        [$from, $to] = $this->chosenWindow();
                        $titulo = match (true) {
                            $from && $to => 'Del '.CarbonImmutable::parse($from)->format('d/m/Y').' al '.CarbonImmutable::parse($to)->format('d/m/Y'),
                            (bool) $from => 'Desde el '.CarbonImmutable::parse($from)->format('d/m/Y'),
                            (bool) $to => 'Hasta el '.CarbonImmutable::parse($to)->format('d/m/Y'),
                            default => 'Todos los períodos',
                        };

                        return app(HorasConfirmadasExcel::class)->download(
                            $query->get(),
                            $titulo,
                            'HORAS-CONFIRMADAS'.($from ? '-'.CarbonImmutable::parse($from)->format('Ymd') : '').($to ? '-'.CarbonImmutable::parse($to)->format('Ymd') : '').'.xlsx',
                        );
                    }),
            ])
            // Tocar la fila abre todo lo del trabajador en el corte.
            ->recordAction('detalleTrabajador')
            ->recordActions([
                EmployeeDetailAction::make(
                    fn (AttendanceDay $record): ?Employee => $record->employee,
                    fn (): array => $this->window(),
                ),
                AttendanceDayActions::adjustHours(),
                AttendanceDayActions::voidDay(),
            ]);
    }
}
