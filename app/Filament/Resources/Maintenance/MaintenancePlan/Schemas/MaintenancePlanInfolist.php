<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\Schemas;

use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenancePlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('plan_number')
                            ->label('Número de plan')
                            ->copyable()
                            ->weight('bold'),
                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean()
                            ->trueColor('success'),
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Equipo')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('equipment.code')->label('Código'),
                        TextEntry::make('equipment.name')->label('Nombre'),
                        TextEntry::make('responsibleUser.name')
                            ->label('Responsable')
                            ->placeholder('Sin asignar'),
                    ]),

                Section::make('Disparador')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('trigger_source')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn (MaintenanceTriggerSource $state): string => $state->color()),
                        TextEntry::make('cadence_mode')
                            ->label('Cadencia')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'fixed' => 'Fija',
                                'floating' => 'Flotante',
                                default => '—',
                            }),
                        TextEntry::make('time_frequency')
                            ->label('Frecuencia de tiempo')
                            ->placeholder('—'),
                        TextEntry::make('meter_interval')
                            ->label('Intervalo horómetro')
                            ->suffix(' h')
                            ->placeholder('—'),
                        TextEntry::make('estimated_duration_minutes')
                            ->label('Duración estimada')
                            ->suffix(' min')
                            ->placeholder('—'),
                    ]),

                Section::make('Configuración Avanzada')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('pause_when_equipment_inactive')
                            ->label('Pausa con equipo inactivo')
                            ->boolean(),
                        TextEntry::make('grace_period_days')
                            ->label('Gracia (días)')
                            ->suffix(' días')
                            ->placeholder('—'),
                        TextEntry::make('grace_meter_hours')
                            ->label('Gracia horómetro')
                            ->suffix(' h')
                            ->placeholder('—'),
                    ]),

                Section::make('Programación actual')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('schedule.next_due_at')
                            ->label('Próximo vencimiento')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Sin programar'),
                        TextEntry::make('schedule.next_due_meter')
                            ->label('Próximo horómetro')
                            ->suffix(' h')
                            ->placeholder('—'),
                        TextEntry::make('schedule.last_completed_at')
                            ->label('Última ejecución')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Nunca ejecutado'),
                        TextEntry::make('schedule.last_completed_meter')
                            ->label('Horómetro en última ej.')
                            ->suffix(' h')
                            ->placeholder('—'),
                        TextEntry::make('schedule.times_executed')
                            ->label('Veces ejecutado')
                            ->placeholder('0'),
                        TextEntry::make('schedule.times_skipped')
                            ->label('Ciclos omitidos')
                            ->placeholder('0'),
                    ]),

                Section::make('Seguimiento')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Creado el')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('last_generated_at')
                            ->label('Última OT generada')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
