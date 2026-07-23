<?php

namespace App\Filament\Pages;

use App\Domain\Assets\Enums\MeterReadingFrequency;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Registro Semanal: el resto de los equipos, leídos una vez por semana.
 */
class WeeklyMeterRegister extends MeterRegister
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Registro Semanal';

    protected static ?string $title = 'Registro Semanal de Horómetros';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.meter-register';

    protected function frequency(): MeterReadingFrequency
    {
        return MeterReadingFrequency::Weekly;
    }

    protected function columnCount(): int
    {
        return 8;
    }

    protected function stepUnit(): string
    {
        return 'week';
    }
}
