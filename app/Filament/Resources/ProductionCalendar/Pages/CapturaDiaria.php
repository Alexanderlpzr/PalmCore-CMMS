<?php

namespace App\Filament\Resources\ProductionCalendar\Pages;

use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Concerns\MesEnCalendario;
use App\Filament\Resources\ProductionCalendar\ProductionCalendarResource;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * La jornada de producción, día a día.
 *
 * Antes esto pedía la semana entera de una vez. Se cambió porque obligaba a esperar al
 * domingo para saber por dónde iba la fruta del mes, y la planta cierra el día cuando
 * cierra el día. Ahora se anota la jornada y debajo se ve el mes con el RFF acumulándose,
 * que es el número por el que se pregunta a mitad de mes.
 *
 * Por dentro no cambia nada: sigue siendo una fila por día, la misma unidad de la que
 * cuelgan la eficiencia, el MTBF y el cierre mensual.
 *
 * Un día en blanco no se escribe. Cero es un dato —un domingo que nunca debía producir— y
 * vacío es un día del que no sabemos nada.
 *
 * @property-read Schema $form
 */
class CapturaDiaria extends Page
{
    use MesEnCalendario;

    protected static string $resource = ProductionCalendarResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $title = 'Producción diaria';

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

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('viewAny', ProductionCalendarDay::class) ?? false, 403);

        $this->data = [
            'plant_id' => $this->defaultPlantId(),
            'calendar_date' => Carbon::today()->toDateString(),
        ];

        $this->loadDay();
    }

    public function getSubheading(): ?string
    {
        return 'Jornada del '.$this->currentDate()->translatedFormat('l j \d\e F \d\e Y');
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
                ->visible(fn (): bool => ! $this->currentDate()->isToday())
                ->action(function (): void {
                    $this->data['calendar_date'] = Carbon::today()->toDateString();
                    $this->loadDay();
                }),

            Action::make('nextDay')
                ->label('Día siguiente')
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconPosition('after')
                ->color('gray')
                // Una jornada que no ha ocurrido no tiene fruta que anotar.
                ->visible(fn (): bool => $this->currentDate()->lt(Carbon::today()))
                ->action(fn () => $this->shiftDay(1)),
        ];
    }

    private function shiftDay(int $days): void
    {
        $this->data['calendar_date'] = $this->currentDate()->addDays($days)->toDateString();

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

                    DatePicker::make('calendar_date')
                        ->label('Fecha')
                        ->required()
                        ->maxDate(Carbon::today())
                        ->live()
                        ->afterStateUpdated(fn () => $this->loadDay()),
                ]),

            Section::make('La jornada')
                ->description('Las horas y la fruta se anotan juntas: el día se cierra una sola vez, y separarlas garantizaba que una de las dos se quedara sin llenar.')
                ->columns(2)
                ->schema([
                    TextInput::make('programmed_hours')
                        ->label('Horas pagadas')
                        ->helperText('Cero es un dato legítimo: un día sin molienda no es un día malo.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(24)
                        ->placeholder('Sin cargar'),

                    TextInput::make('processed_tons')
                        ->label('Fruta procesada (toneladas)')
                        ->helperText('En toneladas, no en kilos. Un día bueno son unas 250 t.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(2000)
                        ->placeholder('0'),

                    Textarea::make('notes')
                        ->label('Notas')
                        ->placeholder('DOMINGO, ASEO/MTTO, FESTIVO…')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // ── Guardado ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $plant = $this->currentPlant();

        abort_unless($plant !== null, 404);
        abort_unless(auth()->user()?->can('create', ProductionCalendarDay::class) ?? false, 403);

        $state = $this->form->getState();

        try {
            $day = app(ProductionCalendarService::class)->upsertDay(
                plant: $plant,
                date: $this->currentDate(),
                hours: $state['programmed_hours'] ?? null,
                tons: $state['processed_tons'] ?? null,
            );
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();

            return;
        }

        if ($day === null) {
            Notification::make()
                ->title('Sin horas, no hay jornada que guardar')
                ->body('Un día en blanco no se escribe: vacío y cero no son lo mismo.')
                ->warning()
                ->send();

            return;
        }

        if (filled($state['notes'] ?? null) || filled($day->notes)) {
            $day->update(['notes' => $state['notes'] ?? null]);
        }

        $this->loadDay();

        Notification::make()
            ->title('Jornada guardada')
            ->body('El RFF acumulado del mes está en la tabla de abajo.')
            ->success()
            ->send();
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
                            ->label('Guardar la jornada')
                            ->submit('save')
                            ->keyBindings(['mod+s'])
                            ->visible(fn (): bool => auth()->user()?->can('create', ProductionCalendarDay::class) ?? false),
                    ])->key('form-actions'),
                ]),

            // El mes debajo, con el RFF acumulándose: es el número por el que se pregunta
            // a mitad de mes, y esperar al cierre para saberlo era la queja de fondo.
            View::make('filament.pages.produccion-mes'),
        ]);
    }

    /**
     * El mes de la fecha elegida, para la tabla de abajo.
     *
     * @return array<string, mixed>
     */
    public function monthTable(): array
    {
        $key = ($this->data['plant_id'] ?? '-').'|'.$this->currentDate()->format('Y-m');

        if (($this->monthCache['key'] ?? null) === $key) {
            return $this->monthCache['table'];
        }

        $plant = $this->currentPlant();

        $table = $plant === null
            ? ['days' => [], 'total_hours' => 0.0, 'total_tons' => 0.0, 'monthLabel' => '']
            : [
                'monthLabel' => ucfirst($this->currentDate()->translatedFormat('F \d\e Y')),
                ...app(ProductionCalendarService::class)->month($plant, $this->currentDate()),
            ];

        $this->monthCache = ['key' => $key, 'table' => $table];

        return $table;
    }

    /**
     * El mes en calendario, encima del formulario.
     *
     * Sale de los mismos días que la tabla de abajo, sin consultar nada nuevo. Un día
     * cuenta como anotado cuando tiene jornada escrita, aunque la fruta sea cero: un
     * domingo de aseo es un día cerrado, no un olvido.
     *
     * @return array<string, mixed>
     */
    public function monthCalendar(): array
    {
        $conDato = [];

        foreach ($this->monthTable()['days'] as $day) {
            if ($day['accumulated_tons'] !== null) {
                $conDato[$day['date']] = true;
            }
        }

        return $this->calendarioDelMes($this->currentDate(), $conDato);
    }

    /**
     * Pliega o despliega el mes de abajo.
     *
     * Empieza desplegado: el RFF acumulándose es la razón por la que el mes está bajo el
     * formulario. Pero treinta filas empujan la jornada fuera de la pantalla, y lo que se
     * hace aquí a diario es cerrar el día, no leer el mes entero.
     */
    public function toggleMes(): void
    {
        $this->mesPlegado = ! $this->mesPlegado;
    }

    /** Lleva el formulario de arriba al día que se pulsó en la tabla. */
    public function goToDay(string $date): void
    {
        $this->data['calendar_date'] = Carbon::parse($date)->toDateString();

        $this->loadDay();
    }

    /** Si esta persona puede escribir jornadas: decide si la tabla pinta celdas o números. */
    public function puedeEscribir(): bool
    {
        return auth()->user()?->can('create', ProductionCalendarDay::class) ?? false;
    }

    /**
     * Corrige las horas o la fruta de un día desde la propia tabla.
     *
     * Pasa por el servicio, que es donde viven el tope de 24 horas y el de 2.000
     * toneladas. La restricción de la base defiende ese techo venga el número por donde
     * venga, pero solo el servicio sabe decir por qué se rechaza — y un número rechazado
     * sin explicación se vuelve a teclear igual.
     *
     * `upsertDay()` exige las horas, así que corregir solo la fruta le pasa las que el día
     * ya tenía. En un día sin fila, escribir fruta sin horas se rechaza: cero y «no sé» no
     * son lo mismo, y una jornada sin horas no es una jornada.
     */
    public function setJornada(string $date, string $campo, ?string $valor): void
    {
        abort_unless(auth()->user()?->can('create', ProductionCalendarDay::class) ?? false, 403);
        abort_unless(in_array($campo, ['programmed_hours', 'processed_tons'], strict: true), 400);

        $plant = $this->currentPlant();

        if ($plant === null) {
            return;
        }

        $dia = Carbon::parse($date)->startOfDay();

        $fila = ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('calendar_date', $dia->toDateString())
            ->first();

        $limpio = ($valor === null || trim($valor) === '') ? null : trim($valor);

        // Sin esto, `(float)` convertiría cualquier texto en un número sin decir nada:
        // «1.250,5» tecleado a la española entra como un 1. Un dato falso que nadie ve es
        // peor que un rechazo.
        if ($limpio !== null && ! is_numeric($limpio)) {
            Notification::make()
                ->title('Eso no es un número')
                ->body('Se escribe con punto decimal y sin separador de miles: 1250.5')
                ->warning()
                ->send();

            $this->loadDay();

            return;
        }

        $horas = $campo === 'programmed_hours' ? $limpio : $fila?->programmed_hours;
        $toneladas = $campo === 'processed_tons' ? $limpio : $fila?->processed_tons;

        try {
            $guardado = app(ProductionCalendarService::class)
                ->upsertDay(plant: $plant, date: $dia, hours: $horas, tons: $toneladas);
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();
            $this->loadDay();

            return;
        }

        if ($guardado === null) {
            Notification::make()
                ->title('Sin horas, no hay jornada que guardar')
                ->body('Un día en blanco no se escribe: vacío y cero no son lo mismo.')
                ->warning()
                ->send();
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

        $plant = $this->currentPlant();

        $row = $plant === null ? null : ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('calendar_date', $this->currentDate()->toDateString())
            ->first();

        $this->data['programmed_hours'] = $row?->programmed_hours;
        $this->data['processed_tons'] = $row?->processed_tons;
        $this->data['notes'] = $row?->notes;
    }

    private function currentDate(): Carbon
    {
        $raw = $this->data['calendar_date'] ?? null;

        return $raw ? Carbon::parse($raw)->startOfDay() : Carbon::today();
    }

    /**
     * La planta se resuelve siempre dentro del tenant activo: aceptar el id que llegue del
     * formulario sin filtrar deja a un tenant escribiendo sobre la planta de otro.
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
        return Plant::where('tenant_id', Filament::getTenant()->id)->orderBy('name')->value('id');
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
