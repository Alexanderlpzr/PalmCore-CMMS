<?php

namespace App\Filament\Resources\Inventory\Warehouse\Schemas;

use App\Models\Warehouse;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del Almacén')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->unique(
                                table: Warehouse::class,
                                column: 'code',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', Filament::getTenant()?->id),
                            ),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: Warehouse::class,
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', Filament::getTenant()?->id),
                            ),
                        TextInput::make('location')
                            ->label('Ubicación')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->hiddenOn('create'),
                    ]),
            ]);
    }
}
