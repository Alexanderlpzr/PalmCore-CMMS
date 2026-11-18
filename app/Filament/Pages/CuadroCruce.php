<?php

namespace App\Filament\Pages;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\PlanVersion;
use App\Domain\Commercial\Services\AnnualPlanService;
use App\Domain\Commercial\Services\CommercialPlanReport;
use App\Domain\Commercial\Services\MonthlyManagementReport;
use App\Domain\Commercial\Services\OilTankService;
use App\Domain\Reports\Excel\ManagementReportExcel;
use App\Domain\Reports\Services\ManagementReportPdfService;
use App\Models\AnnualPlan;
use App\Models\Customer;
use App\Models\FruitSupplier;
use App\Models\SalesDispatch;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Lo que la Directora Administrativa mira: las hojas «Resumen» y «Cuadro Cruce» de su
 * Excel. Por cliente y mes, lo proyectado contra lo despachado, lo facturado y lo pagado;
 * la fruta proyectada contra la recibida; y la cartera.
 *
 * Se compara contra la reproyección —el plan vigente— o contra el original aprobado, para
 * ver qué se esperaba al empezar el año. Todo sale de los despachos y del plan: no hay que
 * tocar nada aquí.
 */
class CuadroCruce extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Cuadro cruce';

    protected static ?string $title = 'Cuadro cruce';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'cuadro-cruce';

    protected string $view = 'filament.pages.cuadro-cruce';

    #[Url(as: 'anio')]
    public ?int $year = null;

    /** Contra qué plan se mide: «reproyeccion» (el vigente) u «original». */
    #[Url(as: 'contra')]
    public string $against = 'reproyeccion';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', SalesDispatch::class) ?? false;
    }

    public function mount(): void
    {
        $this->year ??= (int) now()->format('Y');

        if (! in_array($this->against, [PlanVersion::Original->value, PlanVersion::Reproyeccion->value], true)) {
            $this->against = PlanVersion::Reproyeccion->value;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->reportAction('informePdf', 'En PDF', Heroicon::OutlinedDocumentText, function (Tenant $tenant, int $month): StreamedResponse {
                    $service = app(ManagementReportPdfService::class);
                    $bytes = $service->generate($tenant, (int) $this->year, $month);

                    return response()->streamDownload(fn () => print ($bytes), $service->filename((int) $this->year, $month), ['Content-Type' => 'application/pdf']);
                }),
                $this->reportAction('informeExcel', 'En Excel', Heroicon::OutlinedTableCells, fn (Tenant $tenant, int $month): StreamedResponse => app(ManagementReportExcel::class)->download($tenant, (int) $this->year, $month)),
            ])
                ->label('Informe de gerencia')
                ->icon(Heroicon::OutlinedPresentationChartLine)
                ->button()
                ->color('primary'),
        ];
    }

    /**
     * El informe mensual de gerencia de un mes del año que se mira: el mes y lo acumulado
     * del año hasta él. Propone el último mes con despachos.
     *
     * @param  callable(Tenant, int): StreamedResponse  $download
     */
    private function reportAction(string $name, string $label, Heroicon $icon, callable $download): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->modalHeading(fn (): string => "Informe de gerencia {$this->year}")
            ->modalDescription('El mes elegido y lo acumulado del año hasta ese mes: aceite y palmiste por cliente, facturación, cartera, fruta por proveedor, planta y ofertas abiertas.')
            ->modalSubmitActionLabel('Descargar')
            ->schema([
                Select::make('month')
                    ->label('Mes')
                    ->options(MonthlyManagementReport::MONTHS)
                    ->default(function (): int {
                        $latest = app(MonthlyManagementReport::class)->latestMonth($this->tenantId());

                        return $latest->year === (int) $this->year ? $latest->month : 12;
                    })
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(fn (array $data): StreamedResponse => $download(filament()->getTenant(), (int) $data['month']));
    }

    public function plan(): ?AnnualPlan
    {
        $service = app(AnnualPlanService::class);
        $tenantId = $this->tenantId();

        return $this->against === PlanVersion::Original->value
            ? $service->find($tenantId, (int) $this->year, PlanVersion::Original)
            : $service->current($tenantId, (int) $this->year);
    }

    /**
     * Todo lo que pinta la vista, calculado una vez.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $report = app(CommercialPlanReport::class);
        $tenantId = $this->tenantId();
        $year = (int) $this->year;
        $plan = $this->plan();

        $products = [];

        foreach (CommercialProduct::cases() as $product) {
            $projected = $report->salesPlan($plan, $product);
            $dispatched = $report->dispatched($tenantId, $year, $product);
            $transit = $report->inTransit($tenantId, $year, $product);
            $billing = $report->billing($tenantId, $year, $product);
            $customerIds = array_unique([...array_keys($projected), ...array_keys($dispatched), ...array_keys($transit), ...array_keys($billing)]);

            $rows = [];

            foreach ($customerIds as $id) {
                $planned = array_sum($projected[$id] ?? []);
                $done = array_sum($dispatched[$id] ?? []);
                $onTheWay = $transit[$id]['tons'] ?? 0.0;
                $bill = $billing[$id] ?? ['invoiced_tons' => 0.0, 'uninvoiced_tons' => 0.0, 'invoiced' => 0.0, 'paid' => 0.0, 'receivable' => 0.0, 'invoices_pending' => 0];

                $rows[$id] = [
                    'projected' => round($planned, 2),
                    'dispatched' => round($done, 2),
                    'in_transit' => $onTheWay,
                    'loads_in_transit' => $transit[$id]['loads'] ?? 0,
                    'pending' => round(max($planned - $done - $onTheWay, 0), 2),
                    'ratio' => $planned > 0 ? $done / $planned : null,
                    'projected_months' => $projected[$id] ?? [],
                    'dispatched_months' => $dispatched[$id] ?? [],
                    ...$bill,
                ];
            }

            $products[$product->value] = [
                'rows' => $rows,
                'projected_months' => $report->monthTotals($projected),
                'dispatched_months' => $report->monthTotals($dispatched),
                'invoiced_by_month' => $report->invoicedByMonth($tenantId, $year, $product),
            ];
        }

        $fruitPlan = $report->fruitPlan($plan);
        $fruitReal = $report->fruitReal($tenantId, $year);

        return [
            'products' => $products,
            'fruit_plan' => $fruitPlan,
            'fruit_real' => $fruitReal,
            'fruit_plan_months' => $report->monthTotals($fruitPlan),
            'fruit_real_months' => $report->monthTotals($fruitReal),
            'last_loaded_on' => SalesDispatch::query()->whereYear('loaded_on', $year)->max('loaded_on'),
            'tanks' => app(OilTankService::class)->latest($tenantId),
            'aging' => $report->aging($tenantId),
        ];
    }

    /** @return array<string, array{name: string, market: string}> */
    public function customers(): array
    {
        return Customer::query()->get(['id', 'name', 'market'])
            ->mapWithKeys(fn (Customer $customer): array => [$customer->id => ['name' => $customer->name, 'market' => $customer->market->label()]])
            ->all();
    }

    /** @return array<string, string> */
    public function suppliers(): array
    {
        return FruitSupplier::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** El estado de la facturación de un cliente, en palabras, como el Excel. */
    public static function billingState(array $row): string
    {
        return match (true) {
            $row['dispatched'] <= 0 && $row['invoiced'] <= 0 => 'Sin despachos',
            $row['uninvoiced_tons'] > 0.5 => 'Con tn sin facturar',
            $row['receivable'] > 1 => 'Pendiente de cobro',
            default => 'Al día',
        };
    }

    private function tenantId(): string
    {
        return (string) filament()->getTenant()->getKey();
    }
}
