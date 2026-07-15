<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Domain\Assets\Enums\ComponentStatus;
use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\MaintenancePlan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $title = 'Componentes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->components()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Código')
                    ->maxLength(50),
                TextInput::make('part_number')
                    ->label('N° de parte')
                    ->maxLength(100),
                TextInput::make('manufacturer')
                    ->label('Fabricante')
                    ->maxLength(255),
                TextInput::make('model')
                    ->label('Modelo')
                    ->maxLength(255),
                TextInput::make('serial_number')
                    ->label('N° de serie')
                    ->maxLength(255),
                Select::make('criticality')
                    ->label('Criticidad')
                    ->options(EquipmentCriticality::options())
                    ->default(EquipmentCriticality::Medium)
                    ->required(),
                Select::make('status')
                    ->label('Estado')
                    ->options(ComponentStatus::options())
                    ->default(ComponentStatus::Active)
                    ->required(),
                TextInput::make('useful_life_hours')
                    ->label('Vida útil')
                    ->numeric()
                    ->suffix('h'),
                TextInput::make('worked_hours')
                    ->label('Horas trabajadas')
                    ->numeric()
                    ->suffix('h'),
                TextInput::make('unit_cost')
                    ->label('Valor del repuesto')
                    ->helperText('Costo de la pieza instalada, para llevar el total invertido en el equipo.')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0),
                Textarea::make('notes')
                    ->label('Notas')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            // Sin esto, mostrar «próximo mantenimiento» en cada fila dispararía una
            // consulta por componente (N+1) para traer sus planes y el schedule de cada uno.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('maintenancePlans.schedule'))
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->placeholder('—'),
                TextColumn::make('part_number')
                    ->label('N° de parte')
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('criticality')
                    ->label('Criticidad')
                    ->badge()
                    ->color(fn (EquipmentCriticality $state): string => $state->color())
                    ->formatStateUsing(fn (EquipmentCriticality $state): string => $state->label()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (ComponentStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ComponentStatus $state): string => $state->label()),
                TextColumn::make('worked_hours')
                    ->label('Horas de vida')
                    ->suffix('h')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('next_maintenance')
                    ->label('Próximo mantenimiento')
                    ->getStateUsing(fn (EquipmentComponent $record): ?string => $this->nextMaintenanceSummary($record))
                    ->placeholder('Sin plan de mantenimiento')
                    ->wrap(),
                TextColumn::make('unit_cost')
                    ->label('Valor')
                    ->money(fn (): string => $this->getOwnerRecord()->currency_code ?? 'COP')
                    ->placeholder('—')
                    ->summarize(
                        Sum::make()->money(fn (): string => $this->getOwnerRecord()->currency_code ?? 'COP')
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->tooltip('Registrar un componente o repuesto de este equipo')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = Filament::getTenant()->id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->tooltip('Editar los datos de este componente'),
                DeleteAction::make()
                    ->tooltip('Eliminar este componente'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Uno o dos renglones («Aceite: 320 h · Filtro: 12 días») en vez de mandar al
     * técnico a abrir cada plan para saber qué se le viene a esta pieza. Mezcla
     * planes por horómetro y por fecha sin distinción: al técnico no le interesa
     * cómo se dispara, le interesa cuánto falta.
     */
    private function nextMaintenanceSummary(EquipmentComponent $component): ?string
    {
        /** @var Equipment $equipment */
        $equipment = $this->getOwnerRecord();
        $meterService = app(EquipmentMeterReadingService::class);

        $lines = $component->maintenancePlans
            ->where('is_active', true)
            ->map(function (MaintenancePlan $plan) use ($equipment, $meterService): ?string {
                if ($plan->isMeterBased()) {
                    $remaining = $meterService->metersRemaining($equipment, $plan);

                    return $remaining !== null
                        ? "{$plan->name}: ".number_format($remaining, 0).' h'
                        : null;
                }

                $dueAt = $plan->schedule?->next_due_at;

                return $dueAt !== null
                    ? "{$plan->name}: ".$dueAt->diffForHumans()
                    : null;
            })
            ->filter()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode(' · ') : null;
    }
}
