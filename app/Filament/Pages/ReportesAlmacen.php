<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Domain\Inventory\Services\WarehouseReports;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Los reportes del almacén, sobre el último año del kardex: el ABC por consumo —con el
 * botón para dejar a cada repuesto en su clase—, la plata quieta en el estante, lo que se
 * llevan los equipos y por qué sale lo que sale sin orden de trabajo.
 */
class ReportesAlmacen extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $title = 'Reportes de almacén';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'reportes-almacen';

    protected string $view = 'filament.pages.reportes-almacen';

    public static function canAccess(): bool
    {
        return self::allows('inventory.view');
    }

    public function getSubheading(): ?string
    {
        return 'Los últimos doce meses del kardex, al costo de cada movimiento.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aplicarAbc')
                ->label('Aplicar clasificación ABC')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->authorize(fn (): bool => self::allows('spare-parts.update'))
                ->requiresConfirmation()
                ->modalDescription('Cada repuesto queda con la clase que le da su consumo del último año. De la clase depende cada cuánto se cuenta.')
                ->action(function (): void {
                    $changed = app(WarehouseReports::class)->applyAbc($this->tenant());

                    Notification::make()
                        ->title($changed === 0 ? 'Todos los repuestos ya estaban en su clase' : "{$changed} repuestos cambiaron de clase")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $reports = app(WarehouseReports::class);
        $tenant = $this->tenant();
        $abc = $reports->abc($tenant);
        $exits = $reports->exits($tenant);
        $idle = $reports->idle($tenant);
        $byEquipment = $reports->byEquipment($tenant);
        $inventoryValue = (float) app(WarehouseDashboard::class)->attention($tenant)['inventoryValue'];

        return [
            'hero' => [
                'consumed' => (float) $abc->sum('value'),
                'inventoryValue' => $inventoryValue,
                'idleValue' => (float) $idle->sum('value'),
                'idlePercent' => $inventoryValue > 0 ? $idle->sum('value') * 100 / $inventoryValue : 0,
                'dispatched' => (float) $byEquipment->sum('value'),
                'vouchers' => (float) $exits['reasons']->sum('value'),
                'classA' => $abc->filter(fn (array $row): bool => $row['proposed']->value === 'A')->count(),
            ],
            'abc' => $abc,
            'abcTotals' => $abc->groupBy(fn (array $row): string => $row['proposed']->value)
                ->map(fn ($rows): array => ['parts' => $rows->count(), 'value' => $rows->sum('value')]),
            'idle' => $idle,
            'byEquipment' => $byEquipment,
            'reasons' => $exits['reasons'],
            'recipients' => $exits['recipients'],
        ];
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }

    private static function allows(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can($permission));
    }
}
