<?php

namespace App\Filament\Pages;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\PlanVersion;
use App\Domain\Commercial\Services\AnnualPlanService;
use App\Domain\Commercial\Services\CommercialPlanReport;
use App\Domain\Commercial\Services\OilTankService;
use App\Domain\Commercial\Services\PlanWorkbookImporter;
use App\Domain\Commercial\Services\ProductionWorkbookImporter;
use App\Models\AnnualPlan;
use App\Models\Customer;
use App\Models\FruitSupplier;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * El plan comercial del año, mes a mes: la fruta que entregará cada proveedor, lo que
 * saldrá de ella (aceite, almendra, palmiste), lo que se le venderá a cada cliente y si
 * el aceite alcanza. Las hojas «Proyección Fruta» y «Proyección Mensual» del Excel de la
 * Directora Administrativa.
 *
 * Se llena como una hoja de cálculo: cada celda se guarda al salir de ella. El original
 * se aprueba y queda congelado; los ajustes del año van en la reproyección.
 */
class PlanAnual extends Page
{
    public const MONTHS = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Plan anual';

    protected static ?string $title = 'Plan anual';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'plan-anual';

    protected string $view = 'filament.pages.plan-anual';

    #[Url(as: 'anio')]
    public ?int $year = null;

    #[Url(as: 'version')]
    public ?string $version = null;

    #[Url(as: 'hoja')]
    public string $tab = 'resumen';

    /** Clientes que se agregaron a mano a las ventas de palmiste, aún sin cifras. @var list<string> */
    public array $extraPkoCustomers = [];

    public ?string $pkoCustomerToAdd = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', AnnualPlan::class) ?? false;
    }

    public function mount(): void
    {
        $this->year ??= (int) now()->format('Y');

        if (! in_array($this->version, [PlanVersion::Original->value, PlanVersion::Reproyeccion->value], true)
            || $this->service()->find($this->tenantId(), $this->year, PlanVersion::from($this->version)) === null) {
            $this->version = $this->service()->current($this->tenantId(), $this->year)?->version->value ?? PlanVersion::Original->value;
        }

        if (! in_array($this->tab, ['resumen', 'fruta', 'ventas', 'planta'], true)) {
            $this->tab = 'resumen';
        }
    }

    public function updatedYear(): void
    {
        $this->year = max(2020, min(2100, (int) $this->year));
        $this->version = $this->service()->current($this->tenantId(), $this->year)?->version->value ?? PlanVersion::Original->value;
        $this->extraPkoCustomers = [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar')
                ->label('Importar del Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn (): bool => $this->canManage())
                ->modalHeading('Importar el plan del Excel')
                ->modalDescription('Del libro «Proyección vs Despacho»: la proyección de fruta por proveedor, la capacidad y la TEA de la planta, la proyección de ventas por cliente y la fruta que entregó cada proveedor; y de la planta, las mediciones de los tanques, la maquila y los cortes de inventario de palmiste. El plan entra como original aprobado y su reproyección; si el año ya tiene plan, no se toca. Nada de lo ya importado se repite.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Archivo de Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('commercial-imports')
                        ->maxSize(51200)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $summary = app(PlanWorkbookImporter::class)->import($path, $this->tenantId(), (int) $this->year, $this->user());
                        $production = app(ProductionWorkbookImporter::class)->import($path, $this->tenantId(), $this->user());
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('No se pudo leer el Excel')->body($e->getMessage())->danger()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    $this->version = $this->service()->current($this->tenantId(), (int) $this->year)?->version->value ?? PlanVersion::Original->value;

                    Notification::make()
                        ->title($summary['plan'] ? "Plan {$this->year} importado" : "El plan {$this->year} ya existía: no se tocó")
                        ->body("{$summary['suppliers']} proveedores nuevos, {$summary['receptions']} meses de fruta recibida, {$production['tank_days']} días de tanques, {$production['toll_loads']} cargues a maquila y {$production['inventory_counts']} cortes de inventario.".($summary['warnings'] !== [] ? ' Revise: '.implode(' ', $summary['warnings']) : ''))
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Action::make('crear')
                ->label(fn (): string => "Crear plan {$this->year}")
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => $this->canManage() && $this->service()->find($this->tenantId(), (int) $this->year, PlanVersion::Original) === null)
                ->requiresConfirmation()
                ->modalDescription('Se crea el plan original con sus doce meses. Toma la capacidad y la TEA del plan del año anterior, si lo hay.')
                ->action(function (): void {
                    $this->service()->createOriginal($this->tenantId(), (int) $this->year, $this->user());
                    $this->version = PlanVersion::Original->value;
                    $this->tab = 'fruta';
                }),
            Action::make('aprobar')
                ->label('Aprobar plan original')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('success')
                ->visible(fn (): bool => $this->canManage() && ($plan = $this->plan()) !== null && $plan->version === PlanVersion::Original && $plan->approved_at === null)
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "Aprobar el plan {$this->year}")
                ->modalDescription('El original queda congelado: es contra lo que se medirá el año. Se abre la reproyección, una copia que sí se puede ajustar.')
                ->action(function (): void {
                    $copy = $this->service()->approve($this->plan(), $this->user());
                    $this->version = $copy->version->value;

                    Notification::make()->title('Plan aprobado: los ajustes van en la reproyección')->success()->send();
                }),
        ];
    }

    // ── Celdas ────────────────────────────────────────────────────────────────

    public function guardarFruta(string $supplierId, int $month, ?string $value): void
    {
        $this->save(fn (AnnualPlan $plan) => $this->service()->setFruit($plan, $supplierId, $month, $value));
    }

    public function guardarVenta(string $customerId, string $product, int $month, ?string $value): void
    {
        $this->save(fn (AnnualPlan $plan) => $this->service()->setSale($plan, $customerId, CommercialProduct::from($product), $month, $value));
    }

    public function guardarParametro(string $field, int $month, ?string $value): void
    {
        $this->save(fn (AnnualPlan $plan) => $this->service()->setMonthParameter($plan, $month, $field, $value));
    }

    public function guardarInventarioInicial(?string $value): void
    {
        $this->save(fn (AnnualPlan $plan) => $this->service()->setOpeningOil($plan, $value));
    }

    public function agregarClientePalmiste(): void
    {
        if (filled($this->pkoCustomerToAdd) && ! in_array($this->pkoCustomerToAdd, $this->extraPkoCustomers, true)) {
            $this->extraPkoCustomers[] = $this->pkoCustomerToAdd;
        }

        $this->pkoCustomerToAdd = null;
    }

    private function save(callable $change): void
    {
        $plan = $this->plan();

        if ($plan === null || ! $this->canManage()) {
            return;
        }

        try {
            $change($plan);
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    // ── Para la vista ─────────────────────────────────────────────────────────

    public function plan(): ?AnnualPlan
    {
        $version = PlanVersion::tryFrom((string) $this->version) ?? PlanVersion::Original;

        return $this->service()->find($this->tenantId(), (int) $this->year, $version);
    }

    public function editable(): bool
    {
        return $this->canManage() && ($this->plan()?->isEditable() ?? false);
    }

    /** @return array<string, string> las versiones que existen para el año */
    public function versions(): array
    {
        return collect(PlanVersion::cases())
            ->filter(fn (PlanVersion $version): bool => $this->service()->find($this->tenantId(), (int) $this->year, $version) !== null)
            ->mapWithKeys(fn (PlanVersion $version): array => [$version->value => $version->label()])
            ->all();
    }

    /** @return list<int> */
    public function years(): array
    {
        $years = AnnualPlan::query()->distinct()->pluck('year')->map(fn ($year): int => (int) $year)->all();
        $current = (int) now()->format('Y');

        return collect([...$years, $current - 1, $current, $current + 1, (int) $this->year])->unique()->sort()->values()->all();
    }

    /**
     * @return array{months: array<int, array<string, mixed>>, fruit: array<string, array<int, float>>, cpo: array<string, array<int, float>>, pko: array<string, array<int, float>>}
     */
    public function data(): array
    {
        $plan = $this->plan();
        $report = app(CommercialPlanReport::class);
        $realFruit = $report->monthTotals($report->fruitReal($this->tenantId(), (int) $this->year));

        return [
            'months' => $report->plantMonths($plan, $realFruit),
            'fruit' => $report->fruitPlan($plan),
            'cpo' => $report->salesPlan($plan, CommercialProduct::Cpo),
            'pko' => $report->salesPlan($plan, CommercialProduct::Pko),
            // El aceite que de verdad salió, según la medición de los tanques.
            'produced' => app(OilTankService::class)->monthlyProduction($this->tenantId(), (int) $this->year),
        ];
    }

    /** Los proveedores activos y los que tengan fruta en el plan. @return Collection<int, FruitSupplier> */
    public function suppliers(array $withValues): Collection
    {
        return FruitSupplier::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', array_keys($withValues)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<string, string> id → nombre de los clientes */
    public function customers(): Collection
    {
        return Customer::query()->orderBy('name')->pluck('name', 'id');
    }

    /**
     * Las filas de ventas de un producto: todos los clientes activos en el aceite; en el
     * palmiste, los que tengan cifras o se hayan agregado. Al final, «Por concretar».
     *
     * @return array<string, string>
     */
    public function salesRows(CommercialProduct $product, array $withValues): array
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'is_active']);

        $rows = $customers
            ->filter(fn (Customer $customer): bool => isset($withValues[$customer->id])
                || ($product === CommercialProduct::Cpo && $customer->is_active)
                || ($product === CommercialProduct::Pko && in_array($customer->id, $this->extraPkoCustomers, true)))
            ->mapWithKeys(fn (Customer $customer): array => [$customer->id => $customer->name])
            ->all();

        return [...$rows, AnnualPlanService::UNASSIGNED => 'Por concretar'];
    }

    private function canManage(): bool
    {
        return auth()->user()?->can('create', AnnualPlan::class) ?? false;
    }

    private function service(): AnnualPlanService
    {
        return app(AnnualPlanService::class);
    }

    private function tenantId(): string
    {
        return (string) filament()->getTenant()->getKey();
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
