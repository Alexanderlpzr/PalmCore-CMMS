<?php

namespace App\Filament\Resources\ProductionCalendar\Tables;

use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ProductionCalendarTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Agrupada por mes y plegable, como los equipos por sección. Una lista de
            // trescientos días seguidos no dice dónde termina agosto y empieza julio, y
            // aquí se viene a mirar un mes concreto.
            //
            // Lo que se gana de paso: los sumadores de horas y toneladas, que ya estaban,
            // pasan a dar también el total de cada mes. Antes solo sumaban lo que hubiera
            // quedado en pantalla tras filtrar.
            //
            // Los selectores de agrupar y ordenar quedan ocultos porque el mes es la única
            // agrupación con sentido aquí, igual que la sección en Equipos.
            ->groups([
                self::porMes(),
            ])
            ->defaultGroup('calendar_date')
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('calendar_date')
                    ->label('Fecha')
                    ->date('D d/m/Y')
                    ->sortable(),
                TextColumn::make('plant.name')
                    ->label('Planta')
                    ->sortable()
                    ->toggleable(),
                // Editable en línea: programar una jornada es escribir un número, no
                // abrir un formulario.
                TextInputColumn::make('programmed_hours')
                    ->label('Horas pagadas')
                    ->type('number')
                    ->rules(['numeric', 'min:0', 'max:24'])
                    ->summarize(Sum::make()->label('Total')),
                // La tonelada se captura junto a la hora, no en otra pantalla: el
                // día se cierra una vez, y separarlas garantizaba que una de las
                // dos se quedara sin llenar.
                TextInputColumn::make('processed_tons')
                    ->label('Fruta procesada (t)')
                    ->type('number')
                    // El techo de cordura: en toneladas, no en kilos.
                    ->rules(['numeric', 'min:0', 'max:2000'])
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->limitWithTooltip(40)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => Plant::orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('calendar_date', 'desc');
    }

    /**
     * El mes como grupo, sobre una columna que guarda días.
     *
     * Filament agrupa por el valor de una columna, y `calendar_date` tiene uno distinto
     * cada día: agruparla tal cual daría trescientos grupos de una fila. Así que las
     * cuatro piezas del grupo se escriben a mano, y las cuatro tienen que coincidir en qué
     * entienden por «mes» — si una discrepa, los totales de cada mes salen mal sin que
     * nada avise:
     *
     *   - la clave: «2026-08», lo que identifica al grupo
     *   - el título: «Agosto de 2026», lo que se lee en la banda
     *   - el orden: por fecha descendente, el mes en curso arriba, como el orden de la
     *     tabla — con el orden por defecto los meses subirían de enero a diciembre
     *     mientras los días de dentro bajan
     *   - el alcance: de qué filas se suma el total del mes
     */
    private static function porMes(): Group
    {
        return Group::make('calendar_date')
            ->label('Mes')
            ->collapsible()
            ->getKeyFromRecordUsing(
                fn (ProductionCalendarDay $record): string => $record->calendar_date->format('Y-m'),
            )
            ->getTitleFromRecordUsing(
                fn (ProductionCalendarDay $record): string => ucfirst(
                    $record->calendar_date->translatedFormat('F \d\e Y'),
                ),
            )
            ->orderQueryUsing(
                fn (Builder $query): Builder => $query->orderBy('calendar_date', 'desc'),
            )
            // Por rango de fechas y no por `to_char(...)`: el índice de la columna sigue
            // sirviendo, y una función sobre la columna lo dejaría fuera.
            ->scopeQueryByKeyUsing(function (Builder $query, ?string $key): Builder {
                if (blank($key)) {
                    return $query;
                }

                $inicio = Carbon::createFromFormat('Y-m-d', $key.'-01')->startOfMonth();

                return $query->whereBetween('calendar_date', [
                    $inicio->toDateString(),
                    $inicio->copy()->endOfMonth()->toDateString(),
                ]);
            })
            // Esta sí agrupa en SQL, y tiene que producir exactamente «2026-08»: Filament
            // indexa por el valor que devuelva y lo busca por la clave de arriba.
            ->groupQueryUsing(
                fn ($query) => $query->groupByRaw("to_char(calendar_date, 'YYYY-MM')"),
            );
    }
}
