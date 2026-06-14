<?php

namespace App\Filament\Resources\Equipment\Tables;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
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
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class EquipmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (EquipmentStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('criticality')
                    ->label('Criticidad')
                    ->badge()
                    ->color(fn (EquipmentCriticality $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (EquipmentPriority $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('plant.name')
                    ->label('Planta')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('area.name')
                    ->label('Área')
                    ->searchable()
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
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EquipmentStatus::options()),
                SelectFilter::make('criticality')
                    ->label('Criticidad')
                    ->options(EquipmentCriticality::options()),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(EquipmentPriority::options()),
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
                ViewAction::make(),
                EditAction::make(),
                Action::make('view_qr')
                    ->label('Ver QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('info')
                    ->modalHeading(fn (Equipment $record): string => 'QR — '.$record->code)
                    ->modalWidth('sm')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->registerModalActions([
                        Action::make('regenerate')
                            ->label('Regenerar QR')
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
