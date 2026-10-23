<?php

namespace App\Livewire\HumanResources;

use App\Filament\Pages\FactorDeHoras;
use App\Models\Tenant;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El indicador «factor de horas» de los tableros, cargado después de la página: el
 * tablero se ve enseguida y la tabla llega sola, con su silueta mientras tanto, en vez
 * de que toda la página espere a que se calcule el corte.
 */
#[Lazy]
class HoursFactorIndicator extends Component
{
    #[Locked]
    public string $tenantId = '';

    public function mount(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function placeholder(): View
    {
        return view('filament.pages.partials.indicador-factor-cargando');
    }

    public function render(): View
    {
        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);

        // Solo para quien entra a esa empresa y ve la nómina: trae sueldos.
        $factor = $tenant && (auth()->user()?->canAccessTenant($tenant) ?? false)
            ? FactorDeHoras::dashboardIndicator($tenant)
            : null;

        return view('filament.pages.partials.indicador-factor', ['factor' => $factor]);
    }
}
