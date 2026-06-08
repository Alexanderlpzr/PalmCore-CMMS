<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Schemas;

use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Models\Equipment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MaintenanceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Solicitud')
                    ->columns(2)
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Equipo')
                            ->options(fn (): array => Equipment::orderBy('code')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->required(),
                        Select::make('request_type')
                            ->label('Tipo')
                            ->options(MaintenanceRequestType::options())
                            ->required(),
                        Select::make('priority')
                            ->label('Prioridad')
                            ->options(MaintenanceRequestPriority::options())
                            ->required()
                            ->default(MaintenanceRequestPriority::P3Medium->value),
                        DatePicker::make('requested_due_date')
                            ->label('Fecha límite solicitada')
                            ->displayFormat('d/m/Y'),
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
