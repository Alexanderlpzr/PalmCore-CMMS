<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Domain\HumanResources\Enums\BonusType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Las bonificaciones del trabajador, con su vigencia.
 *
 * Cierra el agujero de auditoría más grande del libro de Excel: allí son cifras pegadas a
 * mano, con decimales largos y sin fórmula detrás, y suman 21 millones.
 */
class BonusesRelationManager extends RelationManager
{
    protected static string $relationship = 'bonuses';

    protected static ?string $title = 'Bonificaciones';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->bonuses()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Tipo')
                ->options(BonusType::options())
                ->required()
                ->native(false)
                ->live()
                ->helperText(fn ($state): ?string => $state && BonusType::from($state)->countsIbc()
                    ? 'Constitutiva de salario: entra al IBC de salud y pensión y a la base de prima.'
                    : 'No constitutiva: no entra al IBC ni a la base de prima.'),

            TextInput::make('concept')->label('Concepto')->required()->maxLength(80),
            TextInput::make('amount')->label('Valor')->numeric()->required()->minValue(0)->prefix('$'),

            DatePicker::make('effective_from')
                ->label('Vigente desde')
                ->required()
                ->default(now()->startOfMonth()),
            DatePicker::make('effective_to')
                ->label('Vigente hasta')
                ->default(now()->endOfMonth())
                ->helperText('Para una bonificación de un solo mes, del 1 al 31. Déjelo vacío si se repite indefinidamente.'),

            Textarea::make('notes')->label('Notas')->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('concept')->label('Concepto')->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (BonusType $state): string => $state->label())
                    ->color(fn (BonusType $state): string => $state->color()),
                TextColumn::make('amount')->label('Valor')->money('COP', 0)->alignEnd()->sortable(),
                TextColumn::make('effective_from')->label('Desde')->date('d/m/Y')->sortable(),
                TextColumn::make('effective_to')->label('Hasta')->date('d/m/Y')->placeholder('Indefinida'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
