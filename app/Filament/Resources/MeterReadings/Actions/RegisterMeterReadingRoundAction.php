<?php

namespace App\Filament\Resources\MeterReadings\Actions;

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * La ronda: varios equipos, una sola pasada.
 *
 * Cargar un equipo a la vez tiene sentido para una corrección puntual, pero no
 * para la rutina real de planta — un operador que camina y anota 15 diales, o
 * varios equipos que prenden y apagan a la misma hora. Reutiliza
 * EquipmentMeterReadingService::recordBulk(), que ya tolera que una fila falle
 * sin perder las demás; esto solo le pone una interfaz encima.
 */
class RegisterMeterReadingRoundAction
{
    public static function make(): Action
    {
        return Action::make('registerRound')
            ->label('Registrar ronda')
            ->tooltip('Registra varios horómetros a la vez, con la misma fecha')
            ->icon(Heroicon::OutlinedBolt)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()->can('create', EquipmentMeterReading::class))
            ->modalHeading('Registrar ronda de horómetros')
            ->modalDescription('Elige los equipos y lo que marca cada dial hoy. Solo aparecen los equipos con un plan de mantenimiento por horómetro activo.')
            ->modalSubmitActionLabel('Guardar ronda')
            ->schema([
                DateTimePicker::make('recorded_at')
                    ->label('Momento de la ronda')
                    ->helperText('Se aplica a todas las lecturas de esta ronda.')
                    ->seconds(false)
                    ->default(now())
                    ->native(false)
                    ->required(),
                Repeater::make('readings')
                    ->label('Equipos')
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Equipo')
                            ->options(fn (): array => self::equipmentOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            // Un mismo equipo no puede aparecer dos veces en la misma
                            // ronda — la segunda lectura pisaría a la primera.
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('reading_value')
                            ->label('Lectura del dial')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Agregar equipo')
                    ->minItems(1)
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $service = app(EquipmentMeterReadingService::class);

                $readings = collect($data['readings'])
                    ->map(fn (array $row): array => [
                        'equipment_id' => $row['equipment_id'],
                        'reading_value' => (float) $row['reading_value'],
                        'recorded_at' => $data['recorded_at'],
                    ])
                    ->all();

                $result = $service->recordBulk(
                    readings: $readings,
                    recordedBy: auth()->user(),
                    tenantId: Filament::getTenant()->id,
                );

                $recordedCount = count($result['recorded']);
                $failedCount = count($result['failed']);

                if ($failedCount === 0) {
                    Notification::make()
                        ->title('Ronda registrada')
                        ->body("{$recordedCount} lectura(s) guardada(s).")
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("{$recordedCount} de ".count($readings).' lecturas guardadas')
                    ->body(collect($result['failed'])
                        ->map(fn (array $failure): string => self::equipmentLabel($failure['equipment_id']).': '.$failure['error'])
                        ->implode(' · '))
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Solo equipos cuyo programa preventivo depende de una lectura. Ofrecer todos
     * los equipos de la planta en una ronda de horómetros sería ruido: la mayoría
     * no tiene ningún plan por horas que alimentar.
     *
     * @return array<string, string>
     */
    private static function equipmentOptions(): array
    {
        return Equipment::whereHas(
            'maintenancePlans',
            fn ($query) => $query->where('is_active', true)->whereIn('trigger_source', [
                MaintenanceTriggerSource::Meter->value,
                MaintenanceTriggerSource::Hybrid->value,
            ])
        )
            ->whereNotIn('status', [EquipmentStatus::Retired->value, EquipmentStatus::Disposed->value])
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Equipment $equipment): array => [
                $equipment->id => "{$equipment->code} — {$equipment->name}".($equipment->current_meter_reading !== null
                    ? " (dial: {$equipment->current_meter_reading})"
                    : ''),
            ])
            ->all();
    }

    private static function equipmentLabel(string $equipmentId): string
    {
        return Equipment::withoutGlobalScopes()->find($equipmentId)?->code ?? $equipmentId;
    }
}
