<?php

namespace App\Filament\Resources\PayrollRuns\Schemas;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PayrollRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre del período')
                ->required()
                ->maxLength(120)
                ->default(fn (): string => 'Nómina de '.now()->translatedFormat('F \d\e Y')),

            DatePicker::make('period_start')
                ->label('Desde')
                ->required()
                ->default(now()->startOfMonth())
                ->live()
                // La ventana de horas se mueve con el período: la de noviembre es del 27
                // de octubre al 26 de noviembre.
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    [$from, $to] = self::defaultHoursWindow($state) ?? [null, null];

                    $set('hours_from', $from);
                    $set('hours_to', $to);
                }),

            DatePicker::make('period_end')
                ->label('Hasta')
                ->required()
                ->afterOrEqual('period_start')
                // El día 30 y no el 31: la nómina colombiana liquida sobre meses de 30
                // días, y así lo hace el libro actual sin excepciones.
                ->default(fn (): string => now()->startOfMonth()->addDays(29)->toDateString())
                ->helperText('La nómina se liquida sobre meses de 30 días, como el libro actual.'),

            DatePicker::make('hours_from')
                ->label('Horas desde')
                ->default(fn (): ?string => self::defaultHoursWindow(now()->startOfMonth())[0] ?? null)
                ->helperText('Con día de corte, las horas van del día siguiente al corte del mes anterior al día de corte de este. Vacío: las horas del período.'),

            DatePicker::make('hours_to')
                ->label('Horas hasta')
                ->afterOrEqual('hours_from')
                ->requiredWith('hours_from')
                ->default(fn (): ?string => self::defaultHoursWindow(now()->startOfMonth())[1] ?? null),

            Textarea::make('notes')->label('Notas')->rows(2),
        ]);
    }

    /**
     * La ventana de horas que le toca al período según el día de corte de la empresa, o
     * null si la empresa liquida por mes calendario.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function defaultHoursWindow(mixed $periodStart): ?array
    {
        $tenant = Filament::getTenant();

        if (! $tenant || blank($periodStart)) {
            return null;
        }

        $start = CarbonImmutable::parse($periodStart);
        $cutoffDay = (int) app(PayrollParameterService::class)->valueOrDefault(PayrollParameter::HoursCutoffDay, $start, $tenant->getKey());
        $window = PayrollRun::hoursWindowFor($start, $cutoffDay);

        return $window ? [$window[0]->toDateString(), $window[1]->toDateString()] : null;
    }
}
