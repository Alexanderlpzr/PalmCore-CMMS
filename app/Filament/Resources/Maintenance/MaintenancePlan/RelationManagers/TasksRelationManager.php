<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\RelationManagers;

use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tareas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Título de la tarea')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Descripción')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('estimated_minutes')
                ->label('Duración estimada')
                ->numeric()
                ->minValue(1)
                ->suffix('min'),
            TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->default(0),
            Repeater::make('checklistItems')
                ->label('Ítems de checklist')
                ->relationship('checklistItems')
                ->schema([
                    TextInput::make('label')
                        ->label('Descripción del ítem')
                        ->required()
                        ->maxLength(255),
                    Select::make('item_type')
                        ->label('Tipo')
                        ->options(MaintenanceChecklistItemType::options())
                        ->required()
                        ->live()
                        ->default(MaintenanceChecklistItemType::Boolean->value),
                    TextInput::make('unit')
                        ->label('Unidad')
                        ->maxLength(30)
                        ->placeholder('ej: °C, bar, mm')
                        ->visible(fn (Get $get): bool => $get('item_type') === MaintenanceChecklistItemType::Numeric->value),
                    TextInput::make('expected_min')
                        ->label('Mínimo esperado')
                        ->numeric()
                        ->visible(fn (Get $get): bool => $get('item_type') === MaintenanceChecklistItemType::Numeric->value),
                    TextInput::make('expected_max')
                        ->label('Máximo esperado')
                        ->numeric()
                        ->visible(fn (Get $get): bool => $get('item_type') === MaintenanceChecklistItemType::Numeric->value),
                    Toggle::make('is_required')
                        ->label('Obligatorio')
                        ->default(true),
                ])
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['tenant_id'] = $this->getOwnerRecord()->tenant_id;

                    return $data;
                })
                ->columnSpanFull()
                ->orderColumn('sort_order')
                ->reorderable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Tarea')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('checklistItems_count')
                    ->label('Ítems')
                    ->counts('checklistItems')
                    ->placeholder('0'),
                TextColumn::make('estimated_minutes')
                    ->label('Min. estimados')
                    ->suffix(' min')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = $this->getOwnerRecord()->tenant_id;

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
