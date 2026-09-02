<?php

namespace App\Filament\Resources\PayrollRuns\RelationManagers;

use App\Domain\Reports\Services\DesprendiblePdfService;
use App\Models\PayrollEntry;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los renglones de la nómina: un trabajador por fila, su desprendible detrás.
 *
 * No hay acciones de crear ni editar, y es a propósito: el renglón se deriva de las horas
 * confirmadas, las novedades, las bonificaciones y los parámetros vigentes. Se corrige
 * arreglando el dato de origen y volviendo a liquidar.
 */
class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Renglones';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->entries()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('employee_name')
            ->columns([
                TextColumn::make('employee_name')->label('Trabajador')->searchable()->sortable(),
                TextColumn::make('document_number')->label('Documento')->alignEnd()->searchable()->toggleable(),

                TextColumn::make('worked_days')->label('Días')->numeric(0)->alignEnd(),

                TextColumn::make('basic_earned')
                    ->label('Básico')
                    ->money('COP', 0)->alignEnd()->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('surcharges_total')
                    ->label('Recargos y extras')
                    ->money('COP', 0)->alignEnd()
                    ->summarize(Sum::make()->label('Total')->money('COP', 0)),

                TextColumn::make('bonuses_total')
                    ->label('Bonificaciones')
                    ->money('COP', 0)->alignEnd()->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('transport_allowance')
                    ->label('Auxilio')
                    ->money('COP', 0)->alignEnd()->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_earned')
                    ->label('Devengado')
                    ->money('COP', 0)->alignEnd()
                    ->summarize(Sum::make()->label('Total')->money('COP', 0)),

                TextColumn::make('total_deducted')
                    ->label('Deducido')
                    ->money('COP', 0)->alignEnd()
                    ->summarize(Sum::make()->label('Total')->money('COP', 0)),

                TextColumn::make('net_pay')
                    ->label('Neto')
                    ->money('COP', 0)->alignEnd()->sortable()
                    ->summarize(Sum::make()->label('Total')->money('COP', 0)),

                IconColumn::make('warnings')
                    ->label('Revisar')
                    ->boolean()
                    ->getStateUsing(fn (PayrollEntry $record): bool => $record->hasWarnings())
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (PayrollEntry $record): ?string => $record->hasWarnings()
                        ? implode(' · ', $record->warnings)
                        : null),
            ])
            ->filters([
                Filter::make('con_avisos')
                    ->label('Solo los que hay que revisar')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('warnings')),
            ])
            ->recordActions([
                Action::make('desprendible')
                    ->label('Desprendible')
                    ->icon('heroicon-o-document-arrow-down')
                    ->authorize(fn (PayrollEntry $record): bool => auth()->user()?->can('print', $record) ?? false)
                    ->action(function (PayrollEntry $record): StreamedResponse {
                        $service = app(DesprendiblePdfService::class);

                        return response()->streamDownload(
                            fn () => print $service->generate($record->tenant_id, $record->id),
                            $service->filename($record->tenant_id, $record->id),
                        );
                    }),
            ]);
    }
}
