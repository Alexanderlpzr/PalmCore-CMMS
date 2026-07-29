<?php

namespace App\Filament\Resources\MeterReadings\Schemas;

use App\Domain\Assets\Services\ReferenceDataService;
use App\Models\Area;
use App\Models\Equipment;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class MeterReadingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('area_filter')
                ->label('Sección')
                ->helperText('Elige la sección para ver solo sus equipos.')
                ->options(fn (): array => ReferenceDataService::allAreas(Filament::getTenant()?->id ?? ''))
                ->getOptionLabelUsing(fn ($value): ?string => $value ? Area::withoutGlobalScopes()->find($value)?->name : null)
                ->native(false)
                ->searchable()
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(fn (Set $set) => $set('equipment_id', null)),
            Select::make('equipment_id')
                ->label('Equipo')
                ->options(fn (Get $get): array => Equipment::query()
                    ->when($get('area_filter'), fn ($query, $areaId) => $query->where('area_id', $areaId))
                    ->orderBy('code')
                    ->get()
                    ->mapWithKeys(fn (Equipment $e): array => [
                        $e->id => "{$e->code} — {$e->name}".($e->current_meter_reading !== null
                            ? " (dial: {$e->current_meter_reading})"
                            : ''),
                    ])
                    ->all())
                ->searchable()
                ->required()
                ->native(false),
            TextInput::make('reading_value')
                ->label('Lectura del dial')
                ->helperText('Lo que marca el horómetro hoy. Si el dial se cambió y el número bajó, regístralo igual: se reconoce como reset y el acumulado no retrocede.')
                ->numeric()
                ->minValue(0)
                ->required(),
            DateTimePicker::make('recorded_at')
                ->label('Momento de la lectura')
                ->seconds(false)
                ->default(now()),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(2),
        ]);
    }
}
