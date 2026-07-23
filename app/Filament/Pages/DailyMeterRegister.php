<?php

namespace App\Filament\Pages;

use App\Domain\Assets\Enums\MeterReadingFrequency;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Registro Diario: los equipos críticos del proceso, leídos todos los días.
 */
class DailyMeterRegister extends MeterRegister
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $navigationLabel = 'Registro Diario';

    protected static ?string $title = 'Registro Diario de Horómetros';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.meter-register';

    protected function frequency(): MeterReadingFrequency
    {
        return MeterReadingFrequency::Daily;
    }

    protected function columnCount(): int
    {
        return 7;
    }

    protected function stepUnit(): string
    {
        return 'day';
    }
}
