<?php

namespace App\Filament\Resources\Maintenance\IssueReport\Tables;

use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IssueReportTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('equipment.code')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('equipment.name')
                    ->label('Nombre del equipo')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('severity')
                    ->label('Severidad')
                    ->badge()
                    ->color(fn (IssueSeverity $state): string => $state->color())
                    ->formatStateUsing(fn (IssueSeverity $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (IssueReportStatus $state): string => $state->color())
                    ->formatStateUsing(fn (IssueReportStatus $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('reporter_name')
                    ->label('Reportado por')
                    ->placeholder('Anónimo')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->label('Severidad')
                    ->options(IssueSeverity::options()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(IssueReportStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
