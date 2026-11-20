<?php

namespace App\Filament\Pages;

use App\Domain\Commercial\Services\AnnualPlanService;
use App\Domain\Commercial\Services\CommercialPlanReport;
use App\Domain\Commercial\Services\OilTankService;
use App\Models\AnnualPlan;
use App\Models\OilTank;
use App\Models\OilTankReading;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * La medición diaria de los tanques de aceite —la hoja «Disponibilidad Aceite» del Excel—:
 * cuánto hay en cada tanque y con qué acidez. De ahí salen solas la producción del día,
 * el aceite disponible para vender y la acidez de la mezcla.
 *
 * La mide la Directora Administrativa. Proceso la tuvo un día y se le quitó (2026-11-19):
 * no ve producción.
 */
class TanquesAceite extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Producción';

    protected static ?string $navigationLabel = 'Tanques de aceite';

    protected static ?string $title = 'Tanques de aceite';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'tanques-de-aceite';

    protected string $view = 'filament.pages.tanques-aceite';

    /** El mes que se mira, «2026-11». */
    #[Url(as: 'mes')]
    public ?string $month = null;

    /** El día que se mide. */
    public ?string $date = null;

    /** @var array<string, array{level: ?string, acidity: ?string}> tanque → lo digitado */
    public array $levels = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', OilTank::class) ?? false;
    }

    public function mount(): void
    {
        $this->date = $this->today()->toDateString();
        $this->month = $this->monthStart()->format('Y-m');
        $this->loadLevels();
    }

    public function updatedDate(): void
    {
        $this->loadLevels();
    }

    public function updatedMonth(): void
    {
        $this->month = $this->monthStart()->format('Y-m');
    }

    /** Lo medido ese día, para corregirlo; vacío si no se ha medido. */
    public function loadLevels(): void
    {
        $date = $this->recordDate();
        $readings = OilTankReading::query()->whereDate('reading_date', $date->toDateString())->get()->keyBy('oil_tank_id');

        $this->levels = $this->tanks()->mapWithKeys(fn (OilTank $tank): array => [$tank->id => [
            'level' => $readings->has($tank->id) ? number_format((float) $readings[$tank->id]->level_tons, 2, ',', '') : null,
            'acidity' => $readings->has($tank->id) && $readings[$tank->id]->acidity_percent !== null ? number_format((float) $readings[$tank->id]->acidity_percent, 2, ',', '') : null,
        ]])->all();
    }

    public function guardar(): void
    {
        if (! $this->canRecord()) {
            return;
        }

        try {
            $user = auth()->user();
            app(OilTankService::class)->record($this->tenantId(), $this->recordDate(), $this->levels, $user instanceof User ? $user : null);
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->month = $this->recordDate()->format('Y-m');
        Notification::make()->title('Medición del '.$this->recordDate()->format('d/m/Y').' guardada')->success()->send();
    }

    // ── Para la vista ─────────────────────────────────────────────────────────

    /** @return Collection<int, OilTank> */
    public function tanks(): Collection
    {
        return OilTank::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return array<string, string> id → nombre de todos los tanques, también los inactivos */
    public function tankNames(): array
    {
        return OilTank::query()->withTrashed()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        $service = app(OilTankService::class);
        $start = $this->monthStart();
        $days = $service->days($this->tenantId(), $start, $start->endOfMonth());
        $production = round(array_sum(array_filter(array_column($days, 'production'))), 2);
        $expected = null;

        if ($this->canSeePlan()) {
            $plan = app(AnnualPlanService::class)->current($this->tenantId(), $start->year);
            $expected = $plan ? app(CommercialPlanReport::class)->plantMonths($plan)[$start->month]['cpo'] : null;
        }

        return [
            'days' => array_reverse($days),
            'latest' => $service->latest($this->tenantId()),
            'production' => $production,
            'expected' => $expected,
        ];
    }

    public function canRecord(): bool
    {
        return auth()->user()?->can('record', OilTank::class) ?? false;
    }

    public function canSeePlan(): bool
    {
        return auth()->user()?->can('viewAny', AnnualPlan::class) ?? false;
    }

    public function today(): CarbonImmutable
    {
        $timezone = filament()->getTenant()?->plantTimezone() ?? 'America/Bogota';

        return CarbonImmutable::parse(CarbonImmutable::now($timezone)->toDateString());
    }

    public function recordDate(): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::parse((string) $this->date)->startOfDay();
        } catch (Throwable) {
            return $this->today();
        }

        return $date->isAfter($this->today()) ? $this->today() : $date;
    }

    public function monthStart(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m', (string) $this->month) ?: $this->today()->startOfMonth();
        } catch (Throwable) {
            return $this->today()->startOfMonth();
        }
    }

    private function tenantId(): string
    {
        return (string) filament()->getTenant()->getKey();
    }
}
