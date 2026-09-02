<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Los descuentos que se repiten: seguro funerario, póliza, libranza.
 *
 * Se declaran una vez y se aplican solos mientras estén vigentes, en lugar de escribirlos
 * mes a mes en la celda de cada trabajador.
 */
class DeductionsRelationManager extends RelationManager
{
    protected static string $relationship = 'deductions';

    protected static ?string $title = 'Descuentos recurrentes';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->deductions()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('concept')->label('Concepto')->required()->maxLength(80),
            TextInput::make('amount')->label('Valor mensual')->numeric()->required()->minValue(0)->prefix('$'),

            DatePicker::make('effective_from')
                ->label('Vigente desde')
                ->required()
                ->default(now()->startOfMonth()),
            DatePicker::make('effective_to')
                ->label('Vigente hasta')
                ->helperText('Un descuento que terminó se cierra, no se borra: reabrir una nómina pasada tiene que volver a aplicarlo.'),

            Textarea::make('notes')->label('Notas')->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('concept')->label('Concepto')->searchable(),
                TextColumn::make('amount')->label('Valor')->money('COP', 0)->alignEnd()->sortable(),
                TextColumn::make('effective_from')->label('Desde')->date('d/m/Y')->sortable(),
                TextColumn::make('effective_to')->label('Hasta')->date('d/m/Y')->placeholder('Vigente'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
