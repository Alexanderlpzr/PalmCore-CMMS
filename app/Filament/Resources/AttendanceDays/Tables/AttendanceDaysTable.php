<?php

namespace App\Filament\Resources\AttendanceDays\Tables;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Filament\Resources\AttendanceDays\AttendanceDayActions;
use App\Filament\Resources\AttendanceScans\AttendanceMarkActions;
use App\Models\AttendanceDay;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->numeric(2)
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
                    ->label('Rec. nocturno')
                    ->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sunday_surcharge_hours')
                    ->label('Rec. dominical')
                    ->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('night_sunday_surcharge_hours')
                    ->label('Rec. noct. dom.')
                    ->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_day_hours')
                    ->label('Extra diurna')
                    ->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_night_hours')
                    ->label('Extra nocturna')
                    ->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_sunday_day_hours')
                    ->label('Extra dom. diurna')
                    ->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_sunday_night_hours')
                    ->label('Extra dom. noct.')
                    ->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),

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
                    ->formatStateUsing(fn (AttendanceDayStatus $state): string => $state->label())
                    ->color(fn (AttendanceDayStatus $state): string => $state->color()),

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

                        Notification::make()->title('Horas confirmadas')->success()->send();
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
                    ->modalHeading('Devolver el día a propuesta')
                    ->modalDescription('Se borra la firma y el día vuelve a poder reconstruirse desde las marcas del reloj.')
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
                                ->title("Se confirmaron {$count} días")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
