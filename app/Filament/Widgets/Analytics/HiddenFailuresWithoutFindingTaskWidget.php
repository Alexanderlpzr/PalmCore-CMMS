<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Maintenance\Enums\FailureConsequenceCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Models\FailureModeAnalysis;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * El pago de haber catalogado consecuencias: una falla oculta que nadie
 * revisa se acumula en silencio hasta combinarse con una segunda falla —
 * es exactamente el escenario que RCM manda cerrar con una tarea de
 * búsqueda (pregunta 7). Esta tabla es la lista de pendientes concreta,
 * no una métrica: cada fila es un hueco real en el programa de
 * mantenimiento hasta que alguien le crea la tarea.
 */
class HiddenFailuresWithoutFindingTaskWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 18;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Fallas Ocultas sin Tarea de Búsqueda')
            ->query(
                FailureModeAnalysis::query()
                    ->where('consequence_category', FailureConsequenceCategory::Hidden->value)
                    ->whereNull('failure_finding_plan_id')
                    ->with(['equipment', 'equipmentComponent'])
            )
            ->columns([
                TextColumn::make('equipment.name')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('equipmentComponent.name')
                    ->label('Pieza')
                    ->placeholder('Todo el equipo'),

                TextColumn::make('failure_mode')
                    ->label('Modo de falla')
                    ->badge()
                    ->formatStateUsing(fn (FailureMode $state): string => $state->label()),

                TextColumn::make('effect_description')
                    ->label('Efecto')
                    ->limit(60)
                    ->placeholder('—'),
            ])
            ->emptyStateHeading('Sin fallas ocultas pendientes')
            ->emptyStateDescription('Todas las fallas ocultas catalogadas ya tienen una tarea de búsqueda activa.')
            ->paginated([10, 25, 50]);
    }
}
