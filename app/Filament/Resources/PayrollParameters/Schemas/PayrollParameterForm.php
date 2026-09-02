<?php

namespace App\Filament\Resources\PayrollParameters\Schemas;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Enums\PayrollParameterUnit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayrollParameterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('key')
                ->label('Parámetro')
                ->options(self::groupedOptions())
                ->required()
                ->searchable()
                ->native(false)
                ->live()
                ->disabledOn('edit')
                ->helperText('Al guardar, la vigencia anterior de este parámetro se cierra el día antes. Las nóminas ya liquidadas no cambian.'),

            TextInput::make('value')
                ->label('Valor')
                ->numeric()
                ->required()
                ->step(0.000001)
                ->minValue(0)
                ->maxValue(fn ($get): float => self::parameterFrom($get('key'))?->unit()->maxValue() ?? 1_000_000_000)
                ->suffix(fn ($get): ?string => self::parameterFrom($get('key'))?->unit()->label())
                ->helperText(fn ($get): ?string => self::valueHint(self::parameterFrom($get('key')))),

            DatePicker::make('effective_from')
                ->label('Vigente desde')
                ->required()
                ->default(now()->startOfMonth())
                ->helperText('El primer día en que este valor se aplica. No se puede abrir una vigencia por detrás de otra que ya exista.'),

            Textarea::make('notes')
                ->label('Por qué cambia')
                ->rows(2)
                ->helperText('La norma, el acta o la circular que lo sustenta. Dentro de un año esta nota es lo único que va a explicar el número.'),
        ]);
    }

    /** @return array<string, array<string, string>> */
    private static function groupedOptions(): array
    {
        $grouped = [];

        foreach (PayrollParameter::cases() as $parameter) {
            $grouped[$parameter->group()][$parameter->value] = $parameter->label();
        }

        return $grouped;
    }

    private static function parameterFrom(mixed $key): ?PayrollParameter
    {
        return is_string($key) ? PayrollParameter::tryFrom($key) : null;
    }

    /**
     * El aviso que evita el error de escribir 35 donde va 0,35.
     */
    private static function valueHint(?PayrollParameter $parameter): ?string
    {
        if (! $parameter) {
            return null;
        }

        return match ($parameter->unit()) {
            PayrollParameterUnit::Factor => 'Se escribe como factor, no como porcentaje: un recargo del 35 % es 0,35.',
            PayrollParameterUnit::HourOfDay => 'Hora del día en decimal: las 21:00 son 21, las 6:30 son 6,5.',
            PayrollParameterUnit::Money => 'En pesos, sin puntos ni separadores.',
            PayrollParameterUnit::Number => null,
        };
    }
}
