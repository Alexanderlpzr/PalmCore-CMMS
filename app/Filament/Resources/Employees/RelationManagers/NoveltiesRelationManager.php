<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Domain\HumanResources\Enums\NoveltyType;
use App\Models\EmployeeNovelty;
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
 * Los días que no fueron días trabajados.
 *
 * Vive colgado del trabajador y no como pantalla aparte porque se captura mirando a la
 * persona: llega la incapacidad de fulano y se registra en la ficha de fulano.
 */
class NoveltiesRelationManager extends RelationManager
{
    protected static string $relationship = 'novelties';

    protected static ?string $title = 'Novedades';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->novelties()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Tipo de novedad')
                ->options(NoveltyType::options())
                ->required()
                ->native(false)
                ->live()
                ->helperText(fn ($state): ?string => $state
                    ? NoveltyType::from($state)->dayValueBasis()->label()
                    : null),

            DatePicker::make('starts_on')->label('Desde')->required(),
            DatePicker::make('ends_on')
                ->label('Hasta')
                ->required()
                ->afterOrEqual('starts_on')
                ->helperText('Se guarda el rango y no la cantidad: así se puede repartir una incapacidad que cruza dos meses.'),

            TextInput::make('reference')
                ->label('Soporte')
                ->maxLength(60)
                ->helperText('Número de incapacidad, acta de vacaciones, memorando.'),

            Textarea::make('notes')->label('Notas')->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Novedad')
                    ->badge()
                    ->formatStateUsing(fn (NoveltyType $state): string => $state->label())
                    ->color(fn (NoveltyType $state): string => $state->color()),
                TextColumn::make('starts_on')->label('Desde')->date('d/m/Y')->sortable(),
                TextColumn::make('ends_on')->label('Hasta')->date('d/m/Y'),
                TextColumn::make('days')
                    ->label('Días')
                    ->alignEnd()
                    ->getStateUsing(fn (EmployeeNovelty $record): int => $record->totalDays()),
                TextColumn::make('reference')->label('Soporte')->placeholder('—')->toggleable(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
