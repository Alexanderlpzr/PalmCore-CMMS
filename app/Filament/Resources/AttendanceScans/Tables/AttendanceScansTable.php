<?php

namespace App\Filament\Resources\AttendanceScans\Tables;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Filament\Resources\AttendanceScans\AttendanceMarkActions;
use App\Models\AttendanceScan;
use App\Models\Employee;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AttendanceScansTable
{
    public static function configure(Table $table): Table
    {
        $timezone = AttendanceMarkActions::timezone();

        return $table
            ->defaultSort('scanned_at', 'desc')
            ->emptyStateHeading('Sin marcas')
            ->emptyStateDescription('Nadie marcó en la puerta con estos filtros.')
            ->columns([
                TextColumn::make('scanned_at')
                    ->label('Fecha y hora')
                    ->dateTime('D d/m/Y H:i', $timezone)
                    ->sortable(),
                TextColumn::make('employee.last_name')
                    ->label('Trabajador')
                    ->getStateUsing(fn (AttendanceScan $record): string => $record->employee?->fullName() ?? '—')
                    ->description(fn (AttendanceScan $record): ?string => $record->employee?->document_number),
                TextColumn::make('direction')
                    ->label('Sentido')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceDirection $state): string => $state->label())
                    ->color(fn (AttendanceDirection $state): string => $state->color()),
                // Texto y no píldora: la tabla ya lleva dos (sentido y estado).
                TextColumn::make('source')
                    ->label('Origen')
                    ->formatStateUsing(fn (string $state): string => $state === 'manual' ? 'A mano' : 'Carné')
                    ->color(fn (string $state): string => $state === 'manual' ? 'warning' : 'gray'),
                TextColumn::make('recordedBy.name')
                    ->label('Registró')
                    ->placeholder('—'),
                TextColumn::make('notes')
                    ->label('Nota')
                    ->limit(40)
                    ->tooltip(fn (AttendanceScan $record): ?string => $record->notes)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('voided_at')
                    ->label('Estado')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (AttendanceScan $record): string => 'Anulada por '.($record->voidedBy?->name ?? '—'))
                    ->tooltip(fn (AttendanceScan $record): ?string => $record->void_reason)
                    ->placeholder('Vigente'),
            ])
            ->filters([
                // Por omisión, el día de hoy en la planta: es lo que se mira a diario. Para
                // buscar la salida que faltó se abre el rango hacia atrás.
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(fn (): string => now($timezone)->toDateString())
                            ->default(fn (): string => now($timezone)->toDateString()),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(fn (): string => now($timezone)->toDateString())
                            ->default(fn (): string => now($timezone)->toDateString()),
                        Toggle::make('sin_salida')
                            ->label('Solo entradas sin salida')
                            ->helperText('El turno ya terminó y nadie marcó la salida.'),
                    ])
                    ->columns(3)
                    ->columnSpan(3)
                    ->query(function (Builder $query, array $data) use ($timezone): Builder {
                        $conFechas = filled($data['desde'] ?? null) || filled($data['hasta'] ?? null);

                        // Sin fechas no se acota, salvo para buscar salidas: el último mes.
                        if (! $conFechas && ! ($data['sin_salida'] ?? false)) {
                            return $query;
                        }

                        [$desde, $hasta] = $conFechas
                            ? self::range($data, $timezone)
                            : [now($timezone)->subMonth()->startOfDay(), now($timezone)->endOfDay()];

                        $query->whereBetween('scanned_at', [$desde->copy()->utc(), $hasta->copy()->utc()]);

                        if ($data['sin_salida'] ?? false) {
                            $query->whereKey(self::entriesWithoutExit($desde, $hasta));
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data) use ($timezone): array {
                        if (blank($data['desde'] ?? null) && blank($data['hasta'] ?? null)) {
                            return ($data['sin_salida'] ?? false) ? ['Entradas sin salida (último mes)'] : [];
                        }

                        [$desde, $hasta] = self::range($data, $timezone);
                        $rango = $desde->isSameDay($hasta)
                            ? 'Día: '.$desde->format('d/m/Y')
                            : 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y');

                        return array_filter([$rango, ($data['sin_salida'] ?? false) ? 'Entradas sin salida' : null]);
                    }),
                SelectFilter::make('employee_id')
                    ->label('Trabajador')
                    ->options(fn (): array => Employee::query()
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->fullName()])
                        ->all())
                    ->searchable(),
                SelectFilter::make('source')
                    ->label('Origen')
                    ->options(['qr' => 'Carné', 'manual' => 'A mano']),
                // Por omisión solo las vigentes: la lista es la que cuenta para las horas.
                TernaryFilter::make('voided_at')
                    ->label('Anuladas')
                    ->placeholder('Solo las vigentes')
                    ->trueLabel('Con las anuladas')
                    ->falseLabel('Solo las anuladas')
                    ->queries(
                        true: fn (Builder $query): Builder => $query,
                        false: fn (Builder $query): Builder => $query->whereNotNull('voided_at'),
                        blank: fn (Builder $query): Builder => $query->whereNull('voided_at'),
                    ),
            ])
            // Los filtros a la vista: el día se cambia sin abrir un menú.
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                AttendanceMarkActions::void(),
            ]);
    }

    /**
     * El rango del filtro en la hora de la planta; sin fechas, hoy. Si las escriben al
     * revés se ordenan solas.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function range(array $data, string $timezone): array
    {
        $hoy = now($timezone)->toDateString();
        $desde = Carbon::parse($data['desde'] ?? $hoy, $timezone)->startOfDay();
        $hasta = Carbon::parse($data['hasta'] ?? $data['desde'] ?? $hoy, $timezone)->endOfDay();

        return $desde->lte($hasta) ? [$desde, $hasta] : [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
    }

    /**
     * Las entradas del rango que nunca se cerraron. Se mira hasta 16 horas después del
     * rango, para que el turno de noche que salió al otro día no aparezca abierto, y se
     * dejan fuera las de las últimas 16 horas: esas personas siguen adentro.
     *
     * @return list<string>
     */
    private static function entriesWithoutExit(Carbon $desde, Carbon $hasta): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [];
        }

        $limite = now()->subHours(AttendanceService::MAX_OPEN_SHIFT_HOURS);

        return app(AttendanceService::class)
            ->openEntries($tenant->getKey(), $desde->copy()->utc(), $hasta->copy()->addHours(AttendanceService::MAX_OPEN_SHIFT_HOURS)->utc())
            ->filter(fn (AttendanceScan $scan): bool => $scan->scanned_at->lte($hasta) && $scan->scanned_at->lte($limite))
            ->pluck('id')
            ->all();
    }
}
