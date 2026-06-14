<?php

namespace App\Filament\Resources\Automation\AutomationRule\Schemas;

use App\Domain\Automation\Enums\AutomationMode;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AutomationRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Regla')
                    ->columns(1)
                    ->schema([
                        Placeholder::make('event_description')
                            ->label('Descripción')
                            ->content(fn ($record) => $record?->event_type?->description() ?? ''),

                        Toggle::make('is_active')
                            ->label('Regla activa')
                            ->helperText('Desactivar detiene completamente esta automatización.'),

                        ToggleButtons::make('mode')
                            ->label('Modo')
                            ->options([
                                AutomationMode::Disabled->value => AutomationMode::Disabled->label(),
                                AutomationMode::NotifyOnly->value => AutomationMode::NotifyOnly->label(),
                                AutomationMode::Automatic->value => AutomationMode::Automatic->label(),
                            ])
                            ->colors([
                                AutomationMode::Disabled->value => AutomationMode::Disabled->color(),
                                AutomationMode::NotifyOnly->value => AutomationMode::NotifyOnly->color(),
                                AutomationMode::Automatic->value => AutomationMode::Automatic->color(),
                            ])
                            ->inline()
                            ->required(),
                    ]),

                Section::make('Configuración')
                    ->description('Parámetros específicos de esta regla (dependen del tipo de evento).')
                    ->collapsible()
                    ->schema([
                        KeyValue::make('configuration')
                            ->label('')
                            ->keyLabel('Parámetro')
                            ->valueLabel('Valor')
                            ->nullable(),
                    ]),
            ]);
    }
}
