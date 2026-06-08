<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Permission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rol')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del rol')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make('Permisos')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('Permisos asignados')
                            ->relationship('permissions', 'name')
                            ->options(
                                Permission::orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->gridDirection('row'),
                    ]),
            ]);
    }
}
