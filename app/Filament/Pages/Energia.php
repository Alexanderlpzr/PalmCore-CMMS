<?php

namespace App\Filament\Pages;

use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Exceptions\BusinessRuleException;
use App\Models\EnergyMeter;
use App\Models\Plant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * La ronda diaria de los contadores de energía.
 *
 * Se teclea lo que marca el contador, no lo que consumió: el consumo lo saca el sistema
 * restando la lectura anterior. Esa es toda la diferencia con la hoja de cálculo que
 * esto reemplaza, y no es cosmética — en esa hoja dos fórmulas de delta restaban la fila
 * equivocada e inflaron la turbina de agosto en 3.706 kWh sin que nadie lo notara. Aquí
 * el consumo no se puede desviar del contador, porque no se escribe.
 *
 * Un contador en blanco no se guarda. Cero es un dato —la turbina parada— y vacío es que
 * nadie pasó a leerlo.
 *
 * @property-read Schema $form
 */
class Energia extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationLabel = 'Energía';

    protected static ?string $title = 'Consumo de energía';

    protected static ?int $navigationSort = 8;

    protected static string $routePath = '/energia';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', EnergyMeter::class) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->data = [
            'plant_id' => $this->defaultPlantId(),
            'reading_date' => Carbon::today()->toDateString(),
            'readings' => [],
        ];

        $this->loadDay();
    }

    public function getSubheading(): ?string
    {
        return 'Lectura del '.$this->readingDate()->translatedFormat('l j \d\e F \d\e Y');
    }

    // ── Navegación ────────────────────────────────────────────────────────────

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousDay')
                ->label('Día anterior')
                ->icon(Heroicon::OutlinedChevronLeft)
                ->color('gray')
                ->action(fn () => $this->shiftDay(-1)),

            Action::make('today')
                ->label('Hoy')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->visible(fn (): bool => ! $this->readingDate()->isToday())
                ->action(function (): void {
                    $this->data['reading_date'] = Carbon::today()->toDateString();
                    $this->loadDay();
                }),

            Action::make('nextDay')
                ->label('Día siguiente')
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconPosition('after')
                ->color('gray')
                // Un contador no se lee por adelantado.
                ->visible(fn (): bool => $this->readingDate()->lt(Carbon::today()))
                ->action(fn () => $this->shiftDay(1)),
        ];
    }

    private function shiftDay(int $days): void
    {
        $this->data['reading_date'] = $this->readingDate()->addDays($days)->toDateString();

        $this->loadDay();
    }

    // ── Formulario ────────────────────────────────────────────────────────────

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('plant_id')
                        ->label('Planta')
                        ->options(fn (): array => $this->plantOptions())
                        ->required()
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated(fn () => $this->loadDay()),

                    DatePicker::make('reading_date')
                        ->label('Fecha de lectura')
                        ->required()
                        ->maxDate(Carbon::today())
                        ->live()
                        ->afterStateUpdated(fn () => $this->loadDay()),
                ]),

            Section::make('Los contadores')
                ->description('Se anota lo que marca el contador. El consumo del día lo calcula el sistema restando la lectura anterior, para que no pueda desviarse del aparato.')
                ->schema($this->meterFields()),
        ]);
    }

    /**
     * @return array<Component>
     */
    private function meterFields(): array
    {
        $fields = [];

        foreach ($this->meters() as $meter) {
            $previous = $this->previousFor($meter);

            $fields[] = Fieldset::make($meter->source->label())
                ->columns(2)
                ->schema([
                    TextInput::make("readings.{$meter->id}.reading_value")
                        ->label('Lectura del contador (kWh)')
                        ->helperText($previous === null
                            ? 'Sin lectura previa: esta será la línea base y no cuenta como consumo.'
                            : 'Anterior: '.number_format((float) $previous->reading_value, 0, ',', '.')
                                .' kWh el '.$previous->reading_date->translatedFormat('d/m/Y'))
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('Sin leer'),

                    Text::make(fn (): string => $this->consumptionLabel($meter)),

                    // Solo aparece cuando el consumo del día se sale de lo habitual. Un
                    // contador acumulado no tiene techo, así que la única forma de
                    // atrapar un dígito de más es compararlo con lo que ese aparato
                    // acostumbra. Se puede confirmar: el guardia se equivoca a veces, y
                    // un sistema que no deja registrar lo que pasó no se usa.
                    Checkbox::make("readings.{$meter->id}.force")
                        ->label('La lectura es correcta, guárdala igual')
                        ->columnSpanFull()
                        ->visible(fn (): bool => $this->warningFor($meter) !== null),
                ]);
        }

        if ($fields === []) {
            $fields[] = Text::make('Esta planta no tiene contadores de energía configurados.');
        }

        return $fields;
    }

    private function consumptionLabel(EnergyMeter $meter): string
    {
        $typed = $this->data['readings'][$meter->id]['reading_value'] ?? null;

        if ($typed === null || $typed === '') {
            return 'Consumo del día: sin leer';
        }

        $previous = $this->previousFor($meter);

        if ($previous === null) {
            return 'Consumo del día: línea base, no cuenta';
        }

        $delta = (float) $typed - (float) $previous->reading_value;

        if ($delta < 0) {
            return 'El contador bajó: se registrará como contador reemplazado, y los '
                .number_format((float) $typed, 0, ',', '.').' kWh contarán como consumo.';
        }

        $aviso = $this->warningFor($meter);

        if ($aviso !== null) {
            return '⚠ '.$aviso;
        }

        return 'Consumo del día: '.number_format($delta, 0, ',', '.').' kWh';
    }

    /** El aviso de plausibilidad para lo que se está tecleando ahora mismo, si lo hay. */
    private function warningFor(EnergyMeter $meter): ?string
    {
        $typed = $this->data['readings'][$meter->id]['reading_value'] ?? null;

        if ($typed === null || $typed === '') {
            return null;
        }

        return app(EnergyMeterReadingService::class)
            ->implausibilityWarning($meter, (float) $typed, $this->readingDate());
    }

    // ── Guardado ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $plant = $this->currentPlant();

        abort_unless($plant !== null, 404);
        abort_unless(auth()->user()?->can('create', EnergyMeter::class) ?? false, 403);

        $state = $this->form->getState();
        $date = $this->readingDate();
        $service = app(EnergyMeterReadingService::class);

        $saved = 0;
        $skipped = 0;

        foreach ($this->meters() as $meter) {
            $value = $state['readings'][$meter->id]['reading_value'] ?? null;

            if ($value === null || $value === '') {
                $skipped++;

                continue;
            }

            try {
                $service->record(
                    meter: $meter,
                    readingValue: (float) $value,
                    recordedBy: auth()->user(),
                    readingDate: $date,
                    force: (bool) ($state['readings'][$meter->id]['force'] ?? false),
                );
                $saved++;
            } catch (BusinessRuleException $e) {
                // El aviso de plausibilidad: no se guarda nada hasta que se confirme, y la
                // casilla ya está a la vista junto al contador que lo disparó.
                Notification::make()
                    ->title('Revisa esa lectura')
                    ->body($e->getMessage())
                    ->warning()
                    ->persistent()
                    ->send();

                return;
            } catch (\InvalidArgumentException $e) {
                Notification::make()
                    ->title($meter->name.': '.$e->getMessage())
                    ->danger()
                    ->send();

                return;
            }
        }

        $this->loadDay();

        Notification::make()
            ->title('Lecturas guardadas')
            ->body("{$saved} contador(es) anotados · {$skipped} sin leer")
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Guardar la ronda')
                            ->submit('save')
                            ->keyBindings(['mod+s'])
                            ->visible(fn (): bool => auth()->user()?->can('create', EnergyMeter::class) ?? false),
                    ])->key('form-actions'),
                ]),

            // El mes debajo de la ronda, no en otra pantalla: el operario anota el día y
            // ve la serie en la que acaba de escribir. Un número raro salta a la vista al
            // lado de los de los días anteriores, no en un informe que se mira aparte.
            View::make('filament.pages.energia-mes'),
        ]);
    }

    /**
     * El mes de la fecha elegida, para la tabla de abajo.
     *
     * @return array<string, mixed>
     */
    public function monthTable(): array
    {
        $meters = $this->meters();

        return [
            'meters' => $meters,
            'monthLabel' => ucfirst($this->readingDate()->translatedFormat('F \d\e Y')),
            ...app(EnergyMeterReadingService::class)->monthReadings($meters, $this->readingDate()),
        ];
    }

    /** Lleva la ronda de arriba al día que se pulsó en la tabla. */
    public function goToDay(string $date): void
    {
        $this->data['reading_date'] = Carbon::parse($date)->toDateString();

        $this->loadDay();
    }

    // ── Estado ────────────────────────────────────────────────────────────────

    private function loadDay(): void
    {
        $date = $this->readingDate()->toDateString();
        $readings = [];

        foreach ($this->meters() as $meter) {
            $existing = $meter->readings()->where('reading_date', $date)->first();

            $readings[$meter->id] = [
                'reading_value' => $existing?->reading_value,
                // La confirmación no se hereda: vale para la lectura que se acaba de
                // teclear, no para la siguiente.
                'force' => false,
            ];
        }

        $this->data['readings'] = $readings;
    }

    /** @return Collection<int, EnergyMeter> */
    private function meters()
    {
        $plant = $this->currentPlant();

        if ($plant === null) {
            return collect();
        }

        return EnergyMeter::query()
            ->where('plant_id', $plant->id)
            ->active()
            ->orderBy('sort_order')
            ->get();
    }

    private function previousFor(EnergyMeter $meter)
    {
        return $meter->readings()
            ->where('reading_date', '<', $this->readingDate()->toDateString())
            ->orderByDesc('reading_date')
            ->first();
    }

    private function readingDate(): Carbon
    {
        $raw = $this->data['reading_date'] ?? null;

        return $raw ? Carbon::parse($raw)->startOfDay() : Carbon::today();
    }

    /**
     * La planta se resuelve siempre dentro del tenant activo: aceptar el id que llegue
     * del formulario sin filtrar deja a un tenant escribiendo sobre la planta de otro.
     */
    private function currentPlant(): ?Plant
    {
        $id = $this->data['plant_id'] ?? null;

        if (blank($id)) {
            return null;
        }

        return Plant::where('tenant_id', Filament::getTenant()->id)->find($id);
    }

    private function defaultPlantId(): ?string
    {
        return Plant::where('tenant_id', Filament::getTenant()->id)
            ->orderBy('name')
            ->value('id');
    }

    /**
     * @return array<string, string>
     */
    private function plantOptions(): array
    {
        return Plant::where('tenant_id', Filament::getTenant()->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
