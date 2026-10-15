<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * La hora en 12 horas: hora del 1 al 12, minutos y a. m. o p. m., como se lee en la
 * planta. Filament solo trae selectores de 0 a 23.
 *
 * Por dentro guarda «HH:MM» en 24 horas, igual que un TimePicker: quien lo usa no tiene
 * que traducir nada.
 */
class TwelveHourTimePicker extends Field
{
    protected string $view = 'filament.forms.components.twelve-hour-time-picker';
}
