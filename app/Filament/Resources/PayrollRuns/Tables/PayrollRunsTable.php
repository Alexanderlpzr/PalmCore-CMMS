<?php

namespace App\Filament\Resources\PayrollRuns\Tables;

use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\HumanResources\Exceptions\PayrollRunException;
use App\Domain\HumanResources\Services\PayrollRunService;
use App\Models\PayrollRun;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('period_start', 'desc')
            ->columns([
                TextColumn::make('name')->label('Período')->searchable()->sortable(),

                TextColumn::make('period_start')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('period_end')->label('Hasta')->date('d/m/Y'),

                TextColumn::make('employee_count')->label('Trabajadores')->alignEnd(),

                TextColumn::make('total_earned')->label('Devengado')->money('COP', 0)->alignEnd(),
                TextColumn::make('total_deducted')->label('Deducido')->money('COP', 0)->alignEnd()->toggleable(),
                TextColumn::make('total_net')->label('Neto')->money('COP', 0)->alignEnd()->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (PayrollRunStatus $state): string => $state->label())
                    ->color(fn (PayrollRunStatus $state): string => $state->color()),

                TextColumn::make('calculated_at')
                    ->label('Liquidada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin liquidar')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PayrollRunStatus::options()),
            ])
            ->recordActions([
                Action::make('liquidar')
                    ->label('Liquidar')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->authorize(fn (PayrollRun $record): bool => auth()->user()?->can('calculate', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Liquidar el período')
                    ->modalDescription(
                        'Rehace todos los renglones desde las horas confirmadas, las novedades y las '
                        .'bonificaciones vigentes. Las horas que nadie firmó no entran.'
                    )
                    ->modalSubmitActionLabel('Liquidar')
                    ->action(function (PayrollRun $record): void {
                        $run = app(PayrollRunService::class)->calculate($record);
                        $conAvisos = $run->entriesWithWarnings()->count();

                        Notification::make()
                            ->title("Se liquidaron {$run->employee_count} trabajadores")
                            ->body($conAvisos > 0
                                ? "{$conAvisos} renglones tienen avisos por revisar antes de cerrar."
                                : 'Neto del período: $ '.number_format((float) $run->total_net, 0, ',', '.'))
                            ->success()
                            ->send();
                    }),

                Action::make('cerrar')
                    ->label('Cerrar')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->authorize(fn (PayrollRun $record): bool => auth()->user()?->can('close', $record) ?? false)
                    ->schema([
                        Checkbox::make('force')
                            ->label('Cerrar aunque haya avisos pendientes')
                            ->helperText('Cerrar con avisos es firmar una nómina que el propio sistema marcó como dudosa.'),
                    ])
                    ->modalHeading('Cerrar la nómina')
                    ->modalDescription('A partir de aquí las cifras no se vuelven a recalcular.')
                    ->modalSubmitActionLabel('Cerrar')
                    ->action(function (PayrollRun $record, array $data): void {
                        try {
                            app(PayrollRunService::class)->close(
                                $record,
                                auth()->user(),
                                force: (bool) ($data['force'] ?? false),
                            );

                            Notification::make()->title('Nómina cerrada')->success()->send();
                        } catch (PayrollRunException $e) {
                            Notification::make()
                                ->title('No se pudo cerrar')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Action::make('reabrir')
                    ->label('Reabrir')
                    ->icon('heroicon-o-lock-open')
                    ->color('gray')
                    ->authorize(fn (PayrollRun $record): bool => auth()->user()?->can('reopen', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Reabrir la nómina')
                    ->modalDescription(
                        'Vuelve a borrador. Los renglones se conservan hasta que la liquide de nuevo, '
                        .'para que los desprendibles ya emitidos sigan coincidiendo con lo que muestra la pantalla.'
                    )
                    ->action(function (PayrollRun $record): void {
                        app(PayrollRunService::class)->reopen($record);

                        Notification::make()->title('Nómina reabierta')->success()->send();
                    }),

                EditAction::make(),
            ]);
    }
}
