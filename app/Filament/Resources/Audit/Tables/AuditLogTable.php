<?php

namespace App\Filament\Resources\Audit\Tables;

use App\Domain\Reports\Excel\TableExcelExport;
use App\Filament\Filters\PeriodFilter;
use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->limitWithTooltip(12)
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
            ->headerActions([
                TableExcelExport::action('Auditoría'),
            ])
            // El período a la vista, encima de la tabla, como en talento humano.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                PeriodFilter::make('created_at', 'Fecha'),
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
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
