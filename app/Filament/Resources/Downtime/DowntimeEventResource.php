<?php

namespace App\Filament\Resources\Downtime;

use App\Filament\Resources\Downtime\Pages\CreateDowntimeEvent;
use App\Filament\Resources\Downtime\Pages\ListDowntimeEvents;
use App\Filament\Resources\Downtime\Schemas\DowntimeEventForm;
use App\Filament\Resources\Downtime\Tables\DowntimeEventsTable;
use App\Models\EquipmentDowntimeEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A3 — los paros dejan de vivir solo en la SPA.
 *
 * No hay página de edición a propósito. Un paro no se edita: se cierra, se
 * clasifica o se firma, y cada una de esas cosas pasa por su servicio, que es donde
 * viven las reglas (solapes, Tipo I no diagnosticable, firma única). Un formulario
 * de edición libre sería la puerta trasera que se salta todas.
 */
class DowntimeEventResource extends Resource
{
    protected static ?string $model = EquipmentDowntimeEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $modelLabel = 'Paro';

    protected static ?string $pluralModelLabel = 'Paros';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 5;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return DowntimeEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DowntimeEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDowntimeEvents::route('/'),
            'create' => CreateDowntimeEvent::route('/create'),
        ];
    }
}
