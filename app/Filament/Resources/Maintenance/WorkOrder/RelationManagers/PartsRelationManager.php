<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartsRelationManager extends RelationManager
{
    protected static string $relationship = 'parts';
    protected static ?string $title = 'Repuestos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('part_code')
                ->label('Código de repuesto')
                ->required()
                ->maxLength(255),
            TextInput::make('description')
                ->label('Descripción')
                ->required()
                ->maxLength(500),
            TextInput::make('quantity')
                ->label('Cantidad')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->default(1),
            Select::make('unit')
                ->label('Unidad')
                ->options([
                    'pcs' => 'Piezas',
                    'kg' => 'Kilogramos',
                    'l' => 'Litros',
                    'm' => 'Metros',
                    'hr' => 'Horas',
                ])
                ->default('pcs')
                ->required(),
            TextInput::make('unit_cost')
                ->label('Costo unitario')
                ->numeric()
                ->required()
                ->minValue(0)
                ->prefix('$'),
            TextInput::make('total_cost')
                ->label('Costo total')
                ->numeric()
                ->prefix('$')
                ->disabled()
                ->helperText('Se calcula automáticamente.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('part_code')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60),
                TextColumn::make('quantity')
                    ->label('Cantidad'),
                TextColumn::make('unit')
                    ->label('Unidad'),
                TextColumn::make('unit_cost')
                    ->label('Costo unit.')
                    ->money('COP'),
                TextColumn::make('total_cost')
                    ->label('Total')
                    ->money('COP'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['total_cost'] = round(($data['quantity'] ?? 0) * ($data['unit_cost'] ?? 0), 2);

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['total_cost'] = round(($data['quantity'] ?? 0) * ($data['unit_cost'] ?? 0), 2);

                        return $data;
                    }),
                DeleteAction::make(),
            ]);
    }
}
