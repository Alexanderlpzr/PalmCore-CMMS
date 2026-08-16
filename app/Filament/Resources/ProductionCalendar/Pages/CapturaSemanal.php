<?php

namespace App\Filament\Resources\ProductionCalendar\Pages;

use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\ProductionCalendar\ProductionCalendarResource;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * La semana de producción, en una sola pantalla.
 *
 * El planificador no cierra días sueltos: cierra la semana, cuando ya sabe cuánta
 * fruta entró. Cargar eso día por día es lo que hace que un CMMS se abandone —el
 * mismo argumento que dio origen a «Programar mes»—, pero el mes no sirve para la
 * tonelada: al programar el mes todavía no se sabe.
 *
 * Por dentro esto **no** guarda semanas. Guarda los siete días, que es la unidad de
 * la que cuelgan la eficiencia, el MTBF y el cierre mensual; una semana a caballo
 * entre dos meses reparte sus días donde corresponde y el cierre ni se entera. La
 * semana es la forma de teclear, no una entidad.
 *
 * Un día en blanco no se escribe, y eso es deliberado: cero y «no sé» no son lo
 * mismo. Un domingo en cero baja el denominador —es un día que nunca debía producir—
 * mientras que un día sin fila es un día del que no sabemos nada.
 *
 * @property-read Schema $form
 */
class CapturaSemanal extends Page
{
    protected static string $resource = ProductionCalendarResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $title = 'Producción semanal';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('viewAny', ProductionCalendarDay::class) ?? false, 403);

        $this->data = [
            'plant_id' => $this->defaultPlantId(),
            'week_of' => Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'days' => [],
        ];

        $this->loadWeek();
    }

    public function getSubheading(): ?string
    {
        $start = $this->weekStart();

        return 'Semana del '.$start->translatedFormat('d \d\e F').
            ' al '.$start->copy()->endOfWeek(Carbon::SUNDAY)->translatedFormat('d \d\e F \d\e Y');
    }

    // ── Navegación ────────────────────────────────────────────────────────────

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousWeek')
                ->label('Semana anterior')
                ->icon(Heroicon::OutlinedChevronLeft)
                ->color('gray')
                ->action(fn () => $this->shiftWeek(-1)),

            Action::make('currentWeek')
                ->label('Esta semana')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->visible(fn (): bool => ! $this->weekStart()->isSameWeek(Carbon::today()))
                ->action(function (): void {
                    $this->data['week_of'] = Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString();
                    $this->loadWeek();
                }),

            Action::make('nextWeek')
                ->label('Semana siguiente')
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconPosition('after')
                ->color('gray')
                // Sin futuro: una semana que no ha ocurrido no tiene fruta que anotar,
                // y dejar programarla desde aquí invita a llenarla de ceros que después
                // nadie distingue de un domingo real.
                ->visible(fn (): bool => $this->weekStart()->lt(Carbon::today()->startOfWeek(Carbon::MONDAY)))
                ->action(fn () => $this->shiftWeek(1)),
        ];
    }

    private function shiftWeek(int $weeks): void
    {
        $this->data['week_of'] = $this->weekStart()->addWeeks($weeks)->toDateString();

        $this->loadWeek();
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
                        ->afterStateUpdated(fn () => $this->loadWeek()),

                    DatePicker::make('week_of')
                        ->label('Semana')
                        ->helperText('Cualquier día sirve: la pantalla se mueve al lunes de esa semana.')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->data['week_of'] = $this->weekStart()->toDateString();
                            $this->loadWeek();
                        }),
                ]),

            Section::make('Los siete días')
                ->description('Un día en blanco no se guarda. Cero es un dato legítimo: un domingo sin molienda no es un día malo, es un día que nunca debía producir.')
                ->schema($this->dayFields()),
        ]);
    }

    /**
     * @return array<Component>
     */
    private function dayFields(): array
    {
        $start = $this->weekStart();
        $fields = [];

        for ($date = $start->copy(); $date->lte($start->copy()->endOfWeek(Carbon::SUNDAY)); $date->addDay()) {
            $key = $date->toDateString();
            $isFuture = $date->isFuture();

            $fields[] = Fieldset::make(ucfirst($date->translatedFormat('l j \d\e F')))
                ->columns(2)
                ->schema([
                    TextInput::make("days.{$key}.programmed_hours")
                        ->label('Horas pagadas')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(24)
                        ->disabled($isFuture)
                        ->placeholder($isFuture ? 'Aún no ocurre' : 'Sin cargar'),

                    TextInput::make("days.{$key}.processed_tons")
                        ->label('Fruta procesada (t)')
                        ->numeric()
                        ->minValue(0)
                        ->disabled($isFuture)
                        ->placeholder($isFuture ? '—' : '0'),
                ]);
        }

        $fields[] = Text::make(fn (): string => $this->weekTotalsLabel());

        return $fields;
    }

    private function weekTotalsLabel(): string
    {
        $days = $this->data['days'] ?? [];

        $hours = 0.0;
        $tons = 0.0;

        foreach ($days as $values) {
            $hours += (float) ($values['programmed_hours'] ?? 0);
            $tons += (float) ($values['processed_tons'] ?? 0);
        }

        return sprintf(
            'Total de la semana: %s h pagadas · %s t de fruta',
            number_format($hours, 2, ',', '.'),
            number_format($tons, 2, ',', '.'),
        );
    }

    // ── Guardado ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $plant = $this->currentPlant();

        abort_unless($plant !== null, 404);
        abort_unless(auth()->user()?->can('create', ProductionCalendarDay::class) ?? false, 403);

        $state = $this->form->getState();

        try {
            $result = app(ProductionCalendarService::class)->upsertWeek(
                plant: $plant,
                weekStart: $this->weekStart(),
                days: $state['days'] ?? [],
            );
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->loadWeek();

        Notification::make()
            ->title('Semana guardada')
            ->body("{$result['created']} días creados · {$result['updated']} actualizados · {$result['skipped']} en blanco")
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
                            ->label('Guardar la semana')
                            ->submit('save')
                            ->keyBindings(['mod+s'])
                            ->visible(fn (): bool => auth()->user()?->can('create', ProductionCalendarDay::class) ?? false),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    // ── Estado ────────────────────────────────────────────────────────────────

    private function loadWeek(): void
    {
        $plant = $this->currentPlant();

        if ($plant === null) {
            $this->data['days'] = [];

            return;
        }

        $week = app(ProductionCalendarService::class)->week($plant, $this->weekStart());

        $this->data['days'] = array_map(
            fn (array $values): array => [
                'programmed_hours' => $values['programmed_hours'],
                'processed_tons' => $values['processed_tons'],
            ],
            $week,
        );
    }

    private function weekStart(): Carbon
    {
        $raw = $this->data['week_of'] ?? null;

        $date = $raw ? Carbon::parse($raw) : Carbon::today();

        return $date->startOfWeek(Carbon::MONDAY);
    }

    /**
     * La planta se resuelve **siempre** dentro del tenant activo. Aceptar el id que
     * llegue del formulario sin filtrar es exactamente la fuga que la auditoría ya
     * encontró una vez: un tenant escribiendo sobre la planta de otro.
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
