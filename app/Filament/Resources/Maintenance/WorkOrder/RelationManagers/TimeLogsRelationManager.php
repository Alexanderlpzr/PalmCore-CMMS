<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TimeLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'timeLogs';

    protected static ?string $title = 'Registro de Tiempo';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Técnico')
                ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->default(auth()->id()),
            DateTimePicker::make('started_at')
                ->label('Inicio')
                ->required()
                ->default(now()),
            DateTimePicker::make('ended_at')
                ->label('Fin')
                ->nullable()
                ->helperText('Dejar vacío si la sesión sigue abierta.'),
            Textarea::make('description')
                ->label('Descripción')
                ->rows(2)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Técnico')
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('En curso…'),
                TextColumn::make('hours')
                    ->label('Horas')
                    ->getStateUsing(fn ($record): string => $record->isOpen()
                        ? (format_hours_minutes($record->computedHours()) ?? '0min').' (abierto)'
                        : (format_hours_minutes($record->hours) ?? '—'))
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60)
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->tooltip('Registrar manualmente un tramo de tiempo trabajado')
                    ->using(function (array $data, WorkOrderService $service): mixed {
                        return $service->logTime(
                            $this->getOwnerRecord(),
                            User::findOrFail($data['user_id']),
                            Carbon::parse($data['started_at']),
                            isset($data['ended_at']) ? Carbon::parse($data['ended_at']) : null,
                            $data['description'] ?? null,
                        );
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->tooltip('Eliminar este registro de tiempo'),
            ]);
    }
}
