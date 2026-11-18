<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Services\DirectionDashboard;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * El tablero de Dirección: la planta, lo comercial, los costos de personal y de
 * mantenimiento y SST en una pantalla, mes a mes y en lo que va del año. Cada área sigue
 * en su módulo; aquí solo se juntan sus totales, sin sueldos de nadie.
 *
 * Lo ve el rol «gerencia-general» (solo consulta) y Fronda.
 */
class TableroDireccion extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Dirección';

    protected static ?string $navigationLabel = 'Tablero de Dirección';

    protected static ?string $title = 'Tablero de Dirección';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'tablero-direccion';

    protected string $view = 'filament.pages.tablero-direccion';

    #[Url(as: 'anio')]
    public ?int $year = null;

    #[Url(as: 'mes')]
    public ?int $month = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->is_super_admin || $user->hasPermissionTo('management.view'));
    }

    public function mount(): void
    {
        $today = CarbonImmutable::now($this->tenant()->plantTimezone() ?? 'America/Bogota');
        $this->year ??= $today->year;
        $this->month ??= $this->year === $today->year ? $today->month : 12;
        $this->month = max(1, min(12, (int) $this->month));
    }

    public function updatedYear(): void
    {
        $this->year = max(2020, min(2100, (int) $this->year));
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return app(DirectionDashboard::class)->build($this->tenant(), (int) $this->year, (int) $this->month);
    }

    private function tenant(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = filament()->getTenant();

        return $tenant;
    }
}
