<?php

namespace App\Filament\Pages;

use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Concerns\MesEnCalendario;
use App\Models\EnergyMeter;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\Plant;
use App\Models\PlantEnergyDailyLog;
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
    use MesEnCalendario;

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

    /**
     * El mes ya resuelto, para no consultarlo dos veces por pintada.
     *
     * El calendario de arriba y la tabla de abajo miran exactamente los mismos días. Se
     * guarda con la clave de lo que lo determina —planta y fecha— para que cambiar
     * cualquiera de las dos lo invalide solo.
     *
     * @var array{key: string, table: array<string, mixed>}|null
     */
    private ?array $monthCache = null;

    /** Si la tabla del mes está plegada bajo su cabecera. */
    public bool $mesPlegado = false;

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

            // La planta eléctrica, en la misma pasada. Antes eran tres renglones de un
            // Excel que nadie llenaba desde el sistema, y las horas se tecleaban aparte
            // pudiendo salir del horómetro que ya se lee.
            Section::make('La planta eléctrica')
                ->description('El horómetro de cada generador, el combustible cargado y las veces que se cambió de fuente. Las horas del mes las calcula el sistema con lo que avanzó el horómetro.')
                ->columns(2)
                ->schema($this->powerPlantFields()),
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

    /**
     * Los campos de la planta eléctrica: un horómetro por generador marcado, más el
     * combustible y los cambios del día.
     *
     * El horómetro se escribe aquí pero **no se guarda aquí**: va a la misma tabla de
     * lecturas de equipo que el módulo de Horómetros, con su cadena de deltas y sus avisos
     * de mantenimiento por horas. Un solo dato, dos pantallas donde verlo.
     *
     * @return array<Component>
     */
    private function powerPlantFields(): array
    {
        $fields = [];

        foreach ($this->generadores() as $generador) {
            $anterior = $this->previousHourMeterFor($generador);

            $fields[] = TextInput::make("power_plant.hours.{$generador->id}")
                ->label('Horómetro · '.$generador->name.' (h)')
                ->helperText($anterior === null
                    ? 'Sin lectura previa: será la línea base y no cuenta como horas trabajadas.'
                    : 'Anterior: '.number_format((float) $anterior->reading_value, 1, ',', '.')
                        .' h el '.$anterior->recorded_at->translatedFormat('d/m/Y'))
                ->numeric()
                ->minValue(0)
                ->placeholder('Sin leer');
        }

        if ($fields === []) {
            $fields[] = Text::make('Ningún equipo de esta planta está marcado como planta eléctrica. Se marca en la ficha del equipo.')
                ->columnSpanFull();
        }

        $fields[] = TextInput::make('power_plant.fuel_gallons')
            ->label('Combustible del día (galones)')
            ->helperText('Cero si no se cargó diésel. En blanco es que nadie lo anotó, y el mes no lo cuenta.')
            ->numeric()
            ->minValue(0)
            ->placeholder('Sin anotar');

        $fields[] = TextInput::make('power_plant.switch_count')
            ->label('Cambios de energía del día')
            ->helperText('Cuántas veces se pasó de una fuente a otra: red, planta eléctrica o turbina.')
            ->integer()
            ->minValue(0)
            ->placeholder('Sin anotar');

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

        $this->savePowerPlant($state, $plant, $date);

        $this->loadDay();

        Notification::make()
            ->title('Lecturas guardadas')
            ->body("{$saved} contador(es) anotados · {$skipped} sin leer")
            ->success()
            ->send();
    }

    /**
     * Guarda lo de la planta eléctrica: horómetros, combustible y cambios de fuente.
     *
     * Los horómetros pasan por el servicio de lecturas de equipo —no se escribe la fila a
     * mano— porque de cada lectura cuelgan el delta, el acumulado y las horas de vida de
     * las piezas. Y si el día ya tenía lectura se **corrige** en vez de añadir una segunda:
     * dos lecturas del mismo día darían un avance inventado en el mes.
     *
     * @param  array<string, mixed>  $state
     */
    private function savePowerPlant(array $state, Plant $plant, Carbon $date): void
    {
        $service = app(EquipmentMeterReadingService::class);

        foreach ($this->generadores() as $generador) {
            $value = $state['power_plant']['hours'][$generador->id] ?? null;

            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }

            $existente = $this->hourMeterOfDay($generador, $date);

            if ($existente !== null) {
                if (abs((float) $existente->reading_value - (float) $value) > 0.001) {
                    $service->updateReading($existente, (float) $value);
                }

                continue;
            }

            $service->record(
                equipment: $generador,
                readingValue: (float) $value,
                recordedBy: auth()->user(),
                recordedAt: $date,
            );
        }

        $galones = $state['power_plant']['fuel_gallons'] ?? null;
        $cambios = $state['power_plant']['switch_count'] ?? null;

        $limpio = fn ($valor): ?float => ($valor === null || $valor === '' || ! is_numeric($valor))
            ? null
            : (float) $valor;

        // Vacío no borra lo que ya estaba anotado: quien no llenó el campo no está diciendo
        // que ese día no hubo combustible, solo que no lo anotó.
        $valores = array_filter([
            'fuel_gallons' => $limpio($galones),
            'energy_switch_count' => $limpio($cambios) === null ? null : (int) $limpio($cambios),
        ], fn (?float $v): bool => $v !== null);

        if ($valores === []) {
            return;
        }

        PlantEnergyDailyLog::withoutGlobalScopes()->updateOrCreate(
            ['plant_id' => $plant->id, 'log_date' => $date->toDateString()],
            [...$valores, 'tenant_id' => $plant->tenant_id, 'recorded_by' => auth()->id()],
        );
    }

    /** Los generadores que cuentan como planta eléctrica en esta planta. */
    private function generadores()
    {
        $plant = $this->currentPlant();

        if ($plant === null) {
            return collect();
        }

        return Equipment::query()
            ->where('plant_id', $plant->id)
            ->where('counts_as_power_plant', true)
            ->orderBy('name')
            ->get();
    }

    /** La lectura del horómetro de ese equipo en ese día, si ya existe. */
    private function hourMeterOfDay(Equipment $generador, Carbon $date): ?EquipmentMeterReading
    {
        return EquipmentMeterReading::query()
            ->where('equipment_id', $generador->id)
            ->whereBetween('recorded_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderByDesc('recorded_at')
            ->first();
    }

    private function previousHourMeterFor(Equipment $generador): ?EquipmentMeterReading
    {
        return EquipmentMeterReading::query()
            ->where('equipment_id', $generador->id)
            ->where('recorded_at', '<', $this->readingDate()->startOfDay())
            ->orderByDesc('recorded_at')
            ->first();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // El calendario primero: la pregunta al abrir la pantalla es «¿qué me falta?»,
            // y responderla obligaba a bajar hasta la tabla del mes y buscar guiones.
            View::make('filament.components.mes-calendario'),

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
        $key = ($this->data['plant_id'] ?? '-').'|'.$this->readingDate()->format('Y-m');

        if (($this->monthCache['key'] ?? null) === $key) {
            return $this->monthCache['table'];
        }

        $meters = $this->meters();

        $table = [
            'meters' => $meters,
            'monthLabel' => ucfirst($this->readingDate()->translatedFormat('F \d\e Y')),
            ...app(EnergyMeterReadingService::class)->monthReadings($meters, $this->readingDate()),
        ];

        $this->monthCache = ['key' => $key, 'table' => $table];

        return $table;
    }

    /**
     * El mes en calendario, encima de la ronda.
     *
     * Sale de los mismos días que la tabla de abajo, sin consultar nada nuevo. Un día
     * cuenta como anotado en cuanto **cualquiera** de los contadores tiene lectura: una
     * ronda a medias sigue siendo una ronda hecha, y marcarla como hueco mandaría a
     * releerlo todo.
     *
     * @return array<string, mixed>
     */
    public function monthCalendar(): array
    {
        $conDato = [];

        foreach ($this->monthTable()['days'] as $day) {
            foreach ($day['cells'] as $celda) {
                if ($celda['accumulated'] !== null) {
                    $conDato[$day['date']] = true;

                    break;
                }
            }
        }

        return $this->calendarioDelMes($this->readingDate(), $conDato);
    }

    /**
     * Pliega o despliega el mes de abajo.
     *
     * Empieza desplegado: la tabla es la razón por la que el mes está bajo el formulario.
     * Pero treinta filas empujan la ronda fuera de la pantalla, y en una jornada normal
     * lo que se hace aquí es anotar hoy, no leer el mes entero.
     */
    public function toggleMes(): void
    {
        $this->mesPlegado = ! $this->mesPlegado;
    }

    /** Lleva la ronda de arriba al día que se pulsó en la tabla. */
    public function goToDay(string $date): void
    {
        $this->data['reading_date'] = Carbon::parse($date)->toDateString();

        $this->loadDay();
    }

    /** Si esta persona puede escribir lecturas: decide si la tabla pinta celdas o números. */
    public function puedeEscribir(): bool
    {
        return auth()->user()?->can('create', EnergyMeter::class) ?? false;
    }

    /**
     * Corrige el acumulado de un día desde la propia tabla.
     *
     * Pasa por el servicio y no escribe la fila directamente, y no es una formalidad: de
     * una lectura cuelga el consumo de ese día **y el de todos los siguientes**. Guardar a
     * mano dejaría los deltas posteriores mintiendo en silencio — exactamente el fallo de
     * la hoja de cálculo que este módulo vino a cerrar.
     *
     * Vaciar la celda borra la lectura, no la pone a cero. Cero afirma que el contador no
     * se movió; vacío dice que nadie pasó a leerlo.
     *
     * El aviso del dígito de más sigue mandando: si el valor no es creíble no se guarda.
     * Confirmarlo a la fuerza se queda solo en el formulario de arriba, donde está la
     * casilla — un clic en la tabla no debería poder saltarse el guardia sin querer.
     */
    public function setLectura(string $meterId, string $date, ?string $valor): void
    {
        abort_unless(auth()->user()?->can('create', EnergyMeter::class) ?? false, 403);

        // El contador se busca entre los de la planta del tenant, no por su id a secas:
        // aceptar el que llegue del navegador deja a un tenant escribiendo sobre el
        // contador de otro.
        $meter = $this->meters()->firstWhere('id', $meterId);

        if ($meter === null) {
            return;
        }

        $dia = Carbon::parse($date)->startOfDay();
        $service = app(EnergyMeterReadingService::class);
        $limpio = ($valor === null || trim($valor) === '') ? null : trim($valor);

        // Sin esto, `(float)` convertiría cualquier texto en un número sin decir nada:
        // «2.519.000» tecleado con puntos entra como un 2. Un dato falso que nadie ve es
        // peor que un rechazo, que es la lección de la hoja que este módulo reemplazó.
        if ($limpio !== null && ! is_numeric($limpio)) {
            Notification::make()
                ->title('Eso no es un número')
                ->body('La lectura del contador se escribe sin puntos ni comas de miles.')
                ->warning()
                ->send();

            $this->loadDay();

            return;
        }

        try {
            if ($limpio === null) {
                $existente = $meter->readings()->where('reading_date', $dia->toDateString())->first();

                if ($existente !== null) {
                    $service->deleteReading($existente);
                }
            } else {
                $service->record(
                    meter: $meter,
                    readingValue: (float) $limpio,
                    recordedBy: auth()->user(),
                    readingDate: $dia,
                );
            }
        } catch (BusinessRuleException $e) {
            Notification::make()
                ->title('Revisa esa lectura')
                ->body($e->getMessage().' Si de verdad es correcta, anótala desde la ronda de arriba.')
                ->warning()
                ->persistent()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title($meter->name.': '.$e->getMessage())->danger()->send();
        }

        // Recargar aunque no se haya escrito: devuelve la celda a lo que hay en la base y
        // deshace lo que el usuario tecleó y fue rechazado.
        $this->loadDay();
    }

    // ── Estado ────────────────────────────────────────────────────────────────

    private function loadDay(): void
    {
        // Guardar no cambia ni la planta ni el mes, así que la clave del cache seguiría
        // siendo la misma y la pantalla mostraría el mes de antes de escribir.
        $this->monthCache = null;

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

        $horas = [];

        foreach ($this->generadores() as $generador) {
            $horas[$generador->id] = $this->hourMeterOfDay($generador, $this->readingDate())?->reading_value;
        }

        $registro = PlantEnergyDailyLog::query()
            ->where('plant_id', $this->data['plant_id'] ?? null)
            ->where('log_date', $date)
            ->first();

        $this->data['power_plant'] = [
            'hours' => $horas,
            'fuel_gallons' => $registro?->fuel_gallons,
            'switch_count' => $registro?->energy_switch_count,
        ];
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
