<?php

namespace App\Filament\Resources\Equipment\Tables;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Enums\MeterReadingFrequency;
use App\Domain\Assets\Services\QrCodeService;
use App\Domain\Assets\Services\ReferenceDataService;
use App\Models\Equipment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class EquipmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Agrupado por sección y colapsable: se abre una sección a la vez para
            // ver sus equipos, igual que la agrupación por equipo en Horómetros.
            // Los selectores de agrupar/ordenar quedan ocultos porque la sección es
            // la única agrupación con sentido operativo: se filtra con el selector
            // de Sección de la barra, no cambiando el criterio de agrupación.
            ->groups([
                Group::make('area.name')
                    ->label('Sección')
                    ->collapsible(),
            ])
            ->defaultGroup('area.name')
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('area.name')
                    ->label('Sección')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->limitWithTooltip(40),
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (EquipmentStatus $state): string => $state->color())
                    ->formatStateUsing(fn (EquipmentStatus $state): string => $state->label())
                    ->sortable(),
                // Criticidad y Prioridad son escalas ordinales: el color sigue
                // informando, pero sin píldora. Con cuatro píldoras seguidas
                // (Estado · Criticidad · Prioridad · Ronda) ninguna destacaba.
                TextColumn::make('criticality')
                    ->label('Criticidad')
                    ->color(fn (EquipmentCriticality $state): string => $state->color())
                    ->formatStateUsing(fn (EquipmentCriticality $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->color(fn (EquipmentPriority $state): string => $state->color())
                    ->formatStateUsing(fn (EquipmentPriority $state): string => $state->label())
                    ->sortable(),
                // Ronda es una categoría nominal (Diaria, Semanal…): colorearla
                // no aporta información, solo compite por la atención.
                TextColumn::make('reading_frequency')
                    ->label('Ronda')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?MeterReadingFrequency $state): string => $state?->label() ?? '—')
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('plant.name')
                    ->label('Planta')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('manufacturer.name')
                    ->label('Fabricante')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('area_id')
                    ->label('Sección')
                    ->options(fn (): array => ReferenceDataService::allAreas(Filament::getTenant()?->id ?? ''))
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EquipmentStatus::options()),
                SelectFilter::make('criticality')
                    ->label('Criticidad')
                    ->options(EquipmentCriticality::options()),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(EquipmentPriority::options()),
                SelectFilter::make('reading_frequency')
                    ->label('Ronda de horómetro')
                    ->options(MeterReadingFrequency::options()),
                SelectFilter::make('plant_id')
                    ->label('Planta')
                    ->options(fn () => ReferenceDataService::plants(Filament::getTenant()?->id ?? ''))
                    ->searchable(),
                SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->options(fn () => ReferenceDataService::categories(Filament::getTenant()?->id ?? ''))
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->tooltip('Ver el detalle de este equipo'),
                EditAction::make()
                    ->tooltip('Editar los datos de este equipo'),
                Action::make('view_qr')
                    ->label('Ver QR')
                    ->tooltip('Muestra el código QR de este equipo para imprimir o escanear')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('info')
                    ->modalHeading(fn (Equipment $record): string => 'QR — '.$record->code)
                    ->modalWidth('sm')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->registerModalActions([
                        Action::make('regenerate')
                            ->label('Regenerar QR')
                            ->tooltip('Genera un nuevo QR e invalida el actual')
                            ->color('warning')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->requiresConfirmation()
                            ->modalHeading('¿Regenerar código QR?')
                            ->modalDescription('El QR actual quedará inactivo. Todos los stickers impresos dejarán de funcionar.')
                            ->action(function (Equipment $record, QrCodeService $service): void {
                                $qrCode = $record->qrCode;

                                if ($qrCode) {
                                    $service->regenerate($qrCode);
                                } else {
                                    $service->createForEquipment($record);
                                }

                                Notification::make()
                                    ->title('QR regenerado correctamente')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->modalContent(fn (Equipment $record, Action $action): View => view(
                        'filament.equipment.qr-modal',
                        [
                            'equipment' => $record,
                            'qrCode' => $record->qrCode,
                            'action' => $action,
                        ]
                    )),
                Action::make('regenerate_qr_direct')
                    ->label('Regenerar QR')
                    ->tooltip('Genera un nuevo QR e invalida el actual')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('¿Regenerar código QR?')
                    ->modalDescription('El QR actual quedará inactivo. Todos los stickers impresos dejarán de funcionar.')
                    ->action(function (Equipment $record, QrCodeService $service): void {
                        $qrCode = $record->qrCode;

                        if ($qrCode) {
                            $service->regenerate($qrCode);
                        } else {
                            $service->createForEquipment($record);
                        }

                        Notification::make()
                            ->title('QR regenerado correctamente')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }
}
