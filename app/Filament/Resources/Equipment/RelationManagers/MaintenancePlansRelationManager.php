<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Filament\Resources\Maintenance\MaintenancePlan\Schemas\MaintenancePlanForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Todos los planes del equipo en un solo lugar — los del equipo entero y los que
 * son de una pieza en particular.
 *
 * Antes de esto, un plan que se creaba desde «Piezas» (con el botón «Programar
 * mantenimiento») no tenía dónde editarse ni borrarse sin salir a buscar el
 * recurso de Mantenimiento y encontrarlo entre todos los planes de todos los
 * equipos. Reutiliza el formulario y la política del recurso de planes: no hay
 * una segunda copia de esas reglas que mantener sincronizada.
 */
class MaintenancePlansRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenancePlans';

    protected static ?string $title = 'Planes de mantenimiento';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->maintenancePlans()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return MaintenancePlanForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['equipmentComponent', 'schedule']))
            ->columns([
                TextColumn::make('plan_number')
                    ->label('Nº Plan')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('equipmentComponent.name')
                    ->label('Pieza')
                    ->placeholder('Todo el equipo')
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('trigger_source')
                    ->label('Disparador')
                    ->badge()
                    ->color(fn (MaintenanceTriggerSource $state): string => $state->color())
                    ->formatStateUsing(fn (MaintenanceTriggerSource $state): string => $state->label()),
                TextColumn::make('meter_interval')
                    ->label('Horómetro')
                    ->suffix(' h')
                    ->placeholder('—'),
                TextColumn::make('time_frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->formatStateUsing(fn (?MaintenanceTimeFrequency $state): ?string => $state?->label())
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('schedule.next_due_at')
                    ->label('Próx. vencimiento')
                    ->date('d/m/Y')
                    ->placeholder('Sin programar')
                    ->toggleable(),
                TextColumn::make('schedule.times_executed')
                    ->label('Ejecuciones')
                    ->placeholder('0')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('trigger_source')
                    ->label('Disparador')
                    ->options(MaintenanceTriggerSource::options()),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->recordActions([
                EditAction::make()
                    ->tooltip('Editar este plan'),
                DeleteAction::make()
                    ->tooltip('Eliminar este plan')
                    ->modalDescription('Las órdenes de trabajo que este plan ya generó no se borran: quedan en el historial sin plan asociado.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
