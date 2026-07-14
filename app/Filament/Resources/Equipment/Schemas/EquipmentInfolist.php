<?php

namespace App\Filament\Resources\Equipment\Schemas;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Models\Equipment;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código de activo'),
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('model')
                            ->label('Modelo')
                            ->placeholder('—'),
                        TextEntry::make('serial_number')
                            ->label('Número de serie')
                            ->placeholder('—'),
                        TextEntry::make('asset_tag')
                            ->label('Etiqueta de activo')
                            ->placeholder('—'),
                    ]),

                Section::make('Clasificación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (EquipmentStatus $state): string => $state->color())
                            ->formatStateUsing(fn (EquipmentStatus $state): string => $state->label()),
                        TextEntry::make('criticality')
                            ->label('Criticidad')
                            ->badge()
                            ->color(fn (EquipmentCriticality $state): string => $state->color())
                            ->formatStateUsing(fn (EquipmentCriticality $state): string => $state->label()),
                        TextEntry::make('priority')
                            ->label('Prioridad')
                            ->badge()
                            ->color(fn (EquipmentPriority $state): string => $state->color())
                            ->formatStateUsing(fn (EquipmentPriority $state): string => $state->label()),
                        TextEntry::make('category.name')
                            ->label('Categoría')
                            ->placeholder('Sin categoría'),
                    ]),

                Section::make('Ubicación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('plant.name')
                            ->label('Planta'),
                        TextEntry::make('area.name')
                            ->label('Área')
                            ->placeholder('—'),
                        TextEntry::make('parent.name')
                            ->label('Equipo padre')
                            ->placeholder('Equipo independiente'),
                        TextEntry::make('location_notes')
                            ->label('Notas de ubicación')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),

                Section::make('Fabricante y Proveedor')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('manufacturer.name')
                            ->label('Fabricante')
                            ->placeholder('—'),
                        TextEntry::make('supplier.name')
                            ->label('Proveedor')
                            ->placeholder('—'),
                    ]),

                Section::make('Ciclo de Vida')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('purchase_date')
                            ->label('Compra')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('installation_date')
                            ->label('Instalación')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('commissioning_date')
                            ->label('Puesta en marcha')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('warranty_expiry_date')
                            ->label('Vencimiento garantía')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('useful_life_years')
                            ->label('Vida útil (años)')
                            ->placeholder('—'),
                        TextEntry::make('retired_at')
                            ->label('Retirado el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Información Financiera')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('purchase_price')
                            ->label('Precio de compra')
                            ->money(fn ($record) => $record->currency_code ?? 'COP')
                            ->placeholder('—'),
                        TextEntry::make('replacement_cost')
                            ->label('Costo de reemplazo')
                            ->money(fn ($record) => $record->currency_code ?? 'COP')
                            ->placeholder('—'),
                        TextEntry::make('currency_code')
                            ->label('Moneda'),
                        TextEntry::make('components_investment_total')
                            ->label('Invertido en repuestos')
                            ->getStateUsing(fn (Equipment $record): float => $record->componentsInvestmentTotal())
                            ->money(fn (Equipment $record) => $record->currency_code ?? 'COP'),
                        TextEntry::make('components_investment_ratio')
                            ->label('% del costo de reemplazo')
                            ->getStateUsing(fn (Equipment $record): ?string => $record->componentsInvestmentRatio() !== null
                                ? number_format($record->componentsInvestmentRatio() * 100, 1).'%'
                                : null
                            )
                            ->placeholder('Sin costo de reemplazo registrado'),
                        TextEntry::make('replacement_recommendation')
                            ->label('Recomendación')
                            ->badge()
                            ->hidden(fn (Equipment $record): bool => $record->componentsInvestmentRatio() === null)
                            ->color(fn (Equipment $record): string => match (true) {
                                $record->componentsInvestmentRatio() >= 0.7 => 'danger',
                                $record->componentsInvestmentRatio() >= 0.5 => 'warning',
                                default => 'success',
                            })
                            ->getStateUsing(fn (Equipment $record): string => match (true) {
                                $record->componentsInvestmentRatio() >= 0.7 => 'Evaluar reemplazo del equipo',
                                $record->componentsInvestmentRatio() >= 0.5 => 'Vigilar — se acerca al punto de reemplazo',
                                default => 'Inversión bajo control',
                            }),
                    ]),

                Section::make('Reliability KPIs')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('kpi_stale_badge')
                            ->label('')
                            ->badge()
                            ->columnSpanFull()
                            ->hidden(fn (Equipment $record): bool => $record->kpi?->is_stale !== true)
                            ->getStateUsing(fn (): string => 'Actualizando…')
                            ->color('warning'),

                        TextEntry::make('kpi_ongoing_badge')
                            ->label('')
                            ->badge()
                            ->columnSpanFull()
                            ->hidden(fn (Equipment $record): bool => $record->ongoingDowntimeEvent === null)
                            ->getStateUsing(fn (): string => 'Evento de parada en curso')
                            ->color('danger'),

                        TextEntry::make('kpi.mtbf_hours')
                            ->label('MTBF')
                            ->getStateUsing(fn (Equipment $record): ?string => $record->kpi
                                ? (float) $record->kpi->mtbf_hours !== 0.0
                                    ? number_format((float) $record->kpi->mtbf_hours, 2).' h'
                                    : 'Sin fallas registradas'
                                : null
                            )
                            ->hintIcon(fn (Equipment $record): ?string => $record->kpi?->mtbf_basis === 'meter' ? 'heroicon-m-clock' : null)
                            ->hint(fn (Equipment $record): ?string => $record->kpi !== null && (float) $record->kpi->mtbf_hours !== 0.0
                                ? ($record->kpi->mtbf_basis === 'meter' ? 'base horómetro' : 'base calendario')
                                : null)
                            ->placeholder('Sin fallas registradas'),

                        TextEntry::make('kpi.mttr_hours')
                            ->label('MTTR')
                            ->getStateUsing(fn (Equipment $record): ?string => $record->kpi
                                ? (float) $record->kpi->mttr_hours !== 0.0
                                    ? number_format((float) $record->kpi->mttr_hours, 2).' h'
                                    : 'Sin fallas registradas'
                                : null
                            )
                            ->placeholder('Sin fallas registradas'),

                        TextEntry::make('kpi.availability_percentage')
                            ->label('Disponibilidad')
                            ->getStateUsing(fn (Equipment $record): ?string => $record->kpi
                                ? number_format((float) $record->kpi->availability_percentage, 2).'%'
                                : null
                            )
                            ->placeholder('—'),

                        TextEntry::make('kpi.unplanned_availability_percentage')
                            ->label('Disp. no planificada')
                            ->getStateUsing(fn (Equipment $record): ?string => $record->kpi
                                ? number_format((float) $record->kpi->unplanned_availability_percentage, 2).'%'
                                : null
                            )
                            ->placeholder('—'),

                        TextEntry::make('kpi.failure_count')
                            ->label('Nº de fallas')
                            ->getStateUsing(fn (Equipment $record): ?string => $record->kpi
                                ? (string) $record->kpi->failure_count
                                : null
                            )
                            ->placeholder('—'),

                        TextEntry::make('kpi.downtime_hours')
                            ->label('Horas de parada')
                            ->getStateUsing(fn (Equipment $record): ?string => $record->kpi
                                ? number_format((float) $record->kpi->downtime_hours, 2).' h'
                                : null
                            )
                            ->placeholder('—'),

                        TextEntry::make('kpi.last_failure_at')
                            ->label('Última falla')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('kpi.last_calculated_at')
                            ->label('Calculado el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Auditoría')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean(),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->columnSpanFull()
                            ->placeholder('Sin notas'),
                        TextEntry::make('createdBy.name')
                            ->label('Creado por')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updatedBy.name')
                            ->label('Actualizado por')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Actualizado')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
