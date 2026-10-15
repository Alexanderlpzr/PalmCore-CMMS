<?php

namespace App\Filament\Resources\AttendanceDays\Tables;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Support\DayTone;
use App\Filament\Resources\AttendanceDays\AttendanceDayActions;
use App\Filament\Resources\AttendanceScans\AttendanceMarkActions;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Support\Hours;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AttendanceDaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No hay días por confirmar')
            ->emptyStateDescription('Todo lo de la lista está firmado. Revise la semana completa en «Revisar semana» o lo ya firmado en «Horas confirmadas».')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->defaultSort('work_date', 'desc')
            // Quién ajustó a mano: lo lee el aviso de las horas.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('adjustedBy:id,name'))
            ->columns([
                TextColumn::make('work_date')
                    ->label('Fecha')
                    ->date('D d/m/Y')
                    ->sortable(),

                TextColumn::make('employee.last_name')
                    ->label('Trabajador')
                    ->getStateUsing(fn (AttendanceDay $record): string => $record->employee?->fullName() ?? '—')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('worked_hours')
                    ->label('Trabajadas')
                    ->formatStateUsing(fn ($state): string => Hours::format($state))
                    ->alignEnd()
                    ->description(fn (AttendanceDay $record): ?string => collect([
                        $record->isManuallyAdjusted() ? 'Ajustado a mano' : null,
                        $record->rest_day_worked ? 'Descanso trabajado' : null,
                        $record->maintenance_support ? 'Apoyo a mantenimiento' : null,
                    ])->filter()->implode(' · ') ?: null)
                    ->tooltip(fn (AttendanceDay $record): ?string => $record->isManuallyAdjusted()
                        ? 'Ajustado por '.($record->adjustedBy?->name ?? '—').' el '.$record->adjusted_at?->format('d/m/Y').': '.$record->adjustment_reason
                        : null)
                    ->sortable(),

                // Las siete bolsas. Ocultas por defecto porque la pantalla se usa para
                // firmar, no para auditar: quien firma mira el total y las anomalías, y
                // abre el detalle solo cuando algo no cuadra.
                TextColumn::make('night_surcharge_hours')
                    ->label('Recargo nocturno')->wrapHeader()
                    ->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sunday_surcharge_hours')
                    ->label('Recargo dominical')->wrapHeader()
                    ->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('night_sunday_surcharge_hours')
                    ->label('Recargo nocturno dominical')->wrapHeader()
                    ->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_day_hours')
                    ->label('Extra diurna')->wrapHeader()
                    ->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_night_hours')
                    ->label('Extra nocturna')->wrapHeader()
                    ->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_sunday_day_hours')
                    ->label('Extra dominical diurna')->wrapHeader()
                    ->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_sunday_night_hours')
                    ->label('Extra dominical nocturna')->wrapHeader()
                    ->formatStateUsing(fn ($state): string => Hours::format($state))->alignEnd()->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('anomalies')
                    ->label('Revisar')
                    ->boolean()
                    ->getStateUsing(fn (AttendanceDay $record): bool => $record->hasAnomalies())
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (AttendanceDay $record): ?string => $record->hasAnomalies()
                        ? implode(' · ', $record->anomalies)
                        : null),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    // El mismo nombre y color que en Revisar semana y en la ficha.
                    ->formatStateUsing(fn (AttendanceDay $record): string => DayTone::label($record))
                    ->color(fn (AttendanceDay $record): string => DayTone::color($record)),

                TextColumn::make('confirmedBy.name')
                    ->label('Firmado por')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(AttendanceDayStatus::options())
                    // Por defecto solo lo que falta por firmar: es la bandeja de trabajo.
                    ->default(AttendanceDayStatus::Propuesta->value),

                SelectFilter::make('employee_id')
                    ->label('Trabajador')
                    ->options(fn (): array => Employee::query()
                        ->active()
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Employee $e): array => [$e->id => $e->fullName()])
                        ->all())
                    ->searchable(),

                Filter::make('con_anomalias')
                    ->label('Solo los que hay que revisar')
                    ->query(fn (Builder $query): Builder => $query->withAnomalies()),
            ])
            // Los de la lista —con sus filtros— que no tienen ningún aviso, de una vez. Los
            // que tienen avisos quedan para revisarlos uno por uno.
            ->headerActions([
                Action::make('confirmarBuenos')
                    ->label('Confirmar lo que está bien')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->authorize(fn (): bool => auth()->user()?->can('correct', AttendanceScan::class) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar los días sin avisos')
                    ->modalDescription(fn (HasTable $livewire): string => ($n = self::cleanProposed($livewire)->count()) > 0
                        ? "Se confirman {$n} ".($n === 1 ? 'día' : 'días').' de la lista sin ningún aviso, hasta hoy. Los que tienen avisos quedan para revisarlos uno por uno.'
                        : 'No hay días sin avisos por confirmar en la lista.')
                    ->action(function (HasTable $livewire): void {
                        $n = app(AttendanceDayConfirmer::class)->confirmMany(self::cleanProposed($livewire), auth()->user());

                        Notification::make()->title(match (true) {
                            $n === 0 => 'No había días para confirmar', $n === 1 => 'Se confirmó 1 día', default => "Se confirmaron {$n} días"
                        })->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('confirmar')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->authorize(fn (AttendanceDay $record): bool => auth()->user()?->can('confirm', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar las horas del día')
                    ->modalDescription(fn (AttendanceDay $record): string => $record->hasAnomalies()
                        ? 'Este día tiene avisos por revisar: '.implode(' · ', $record->anomalies)
                        : 'Al confirmar, estas horas quedan disponibles para liquidar y su nombre queda como responsable.')
                    ->action(function (AttendanceDay $record): void {
                        app(AttendanceDayConfirmer::class)->confirm($record, auth()->user());

                        Notification::make()->title('Día confirmado')->success()->send();
                    }),

                // Corregir el día sin firmar: la hora de sus marcas, la que faltó, sus horas
                // a mano o anularlo. En un menú, para que la fila no se llene de botones;
                // uno confirmado se reabre antes.
                ActionGroup::make([
                    AttendanceDayActions::editMarks(),
                    AttendanceMarkActions::add('agregarMarcaDelDia')
                        ->visible(fn (AttendanceDay $record): bool => $record->status === AttendanceDayStatus::Propuesta)
                        ->fillForm(fn (AttendanceDay $record): array => [
                            'employee_id' => $record->employee_id,
                            // Las 2 p. m. de ese día en la planta; el selector guarda en UTC.
                            'scanned_at' => Carbon::parse($record->work_date->format('Y-m-d').' 14:00', AttendanceMarkActions::timezone())->utc()->toDateTimeString(),
                        ]),
                    AttendanceDayActions::adjustHours(),
                    AttendanceDayActions::restDayWorked(),
                    // Vale también con el día firmado: no cambia lo que se paga.
                    AttendanceDayActions::maintenanceSupport(),
                    AttendanceDayActions::voidDay(),
                ])
                    ->label('Corregir')
                    ->tooltip('Corregir el día'),

                Action::make('reabrir')
                    ->label('Reabrir')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->authorize(fn (AttendanceDay $record): bool => auth()->user()?->can('reopen', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Devolver el día a «Por confirmar»')
                    ->modalDescription('Se borra la firma y el día se vuelve a calcular con las marcas de la portería.')
                    ->action(function (AttendanceDay $record): void {
                        app(AttendanceDayConfirmer::class)->reopen($record);

                        Notification::make()->title('Día reabierto')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('confirmarVarios')
                        ->label('Confirmar seleccionados')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->authorize(fn (): bool => auth()->user()?->can('build', AttendanceDay::class) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar las horas seleccionadas')
                        ->modalDescription(
                            'Los días que ya estaban firmados se dejan como están. '
                            .'Revise antes los que tienen avisos: firmar no los corrige.'
                        )
                        ->action(function (Collection $records): void {
                            $count = app(AttendanceDayConfirmer::class)
                                ->confirmMany($records, auth()->user());

                            Notification::make()
                                ->title($count === 1 ? 'Se confirmó 1 día' : "Se confirmaron {$count} días")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Los por confirmar de la lista filtrada, sin avisos y de hasta hoy.
     *
     * @return Collection<int, AttendanceDay>
     */
    private static function cleanProposed(HasTable $livewire): Collection
    {
        return ($livewire->getFilteredTableQuery() ?? AttendanceDay::query())
            ->clone()
            ->where('status', AttendanceDayStatus::Propuesta)
            ->whereNull('anomalies')
            ->whereDate('work_date', '<=', now(AttendanceMarkActions::timezone())->toDateString())
            ->get();
    }
}
