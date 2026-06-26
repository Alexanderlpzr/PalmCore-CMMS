<?php

namespace App\Filament\Resources\Audit\Tables;

use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AuditLogTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn (AuditLog $record): string => $record->eventLabel())
                    ->color(fn (AuditLog $record): string => $record->eventColor()),

                TextColumn::make('auditable_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable(),

                TextColumn::make('auditable_id')
                    ->label('ID Registro')
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->searchable(),

                TextColumn::make('tenant.name')
                    ->label('Empresa')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Acción')
                    ->options([
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'deleted' => 'Eliminado',
                        'restored' => 'Restaurado',
                    ]),

                SelectFilter::make('auditable_type')
                    ->label('Modelo')
                    ->options(
                        fn (): array => AuditLog::query()
                            ->select('auditable_type')
                            ->distinct()
                            ->pluck('auditable_type')
                            ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)])
                            ->toArray()
                    ),

                SelectFilter::make('tenant_id')
                    ->label('Empresa')
                    ->relationship('tenant', 'name'),

                Filter::make('date_from')
                    ->label('Desde')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Desde')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['date_from'],
                            fn (Builder $q, string $date): Builder => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay())
                        )
                    ),

                Filter::make('date_to')
                    ->label('Hasta')
                    ->form([
                        DatePicker::make('date_to')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['date_to'],
                            fn (Builder $q, string $date): Builder => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay())
                        )
                    ),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
