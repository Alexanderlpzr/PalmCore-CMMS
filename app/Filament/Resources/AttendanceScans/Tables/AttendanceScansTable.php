<?php

namespace App\Filament\Resources\AttendanceScans\Tables;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Filament\Resources\AttendanceScans\AttendanceMarkActions;
use App\Models\AttendanceScan;
use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
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
                // Por omisión, el día de hoy en la planta: es lo que se mira a diario.
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('dia')
                            ->label('Día')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(fn (): string => now($timezone)->toDateString()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['dia'] ?? null,
                        fn (Builder $query, string $day): Builder => $query->whereBetween('scanned_at', [
                            Carbon::parse($day, $timezone)->startOfDay()->utc(),
                            Carbon::parse($day, $timezone)->endOfDay()->utc(),
                        ]),
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($data['dia'] ?? null)
                        ? 'Día: '.Carbon::parse($data['dia'])->format('d/m/Y')
                        : null),
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
            ->recordActions([
                AttendanceMarkActions::void(),
            ]);
    }
}
