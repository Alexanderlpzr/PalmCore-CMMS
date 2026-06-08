<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TechniciansRelationManager extends RelationManager
{
    protected static string $relationship = 'technicians';
    protected static ?string $title = 'Técnicos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Técnico')
                ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Select::make('role')
                ->label('Rol')
                ->options(TechnicianRole::class)
                ->required()
                ->default(TechnicianRole::Technician),
            TextInput::make('planned_hours')
                ->label('Horas planificadas')
                ->numeric()
                ->minValue(0)
                ->suffix('h')
                ->nullable(),
            TextInput::make('hourly_rate')
                ->label('Tarifa por hora')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->helperText('Se congela al asignar. No se recalcula posteriormente.')
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Técnico')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge(),
                TextColumn::make('planned_hours')
                    ->label('H. planif.')
                    ->suffix(' h')
                    ->placeholder('—'),
                TextColumn::make('hourly_rate')
                    ->label('Tarifa/h')
                    ->money('COP')
                    ->placeholder('—'),
                TextColumn::make('laborCost')
                    ->label('Costo MO')
                    ->money('COP')
                    ->getStateUsing(fn ($record) => $record->laborCost())
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, WorkOrderService $service): mixed {
                        return $service->assignTechnician(
                            $this->getOwnerRecord(),
                            User::findOrFail($data['user_id']),
                            $data['role'],
                            $data['planned_hours'] ?? null,
                            $data['hourly_rate'] ?? null,
                        );
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->label('Quitar'),
            ]);
    }
}
