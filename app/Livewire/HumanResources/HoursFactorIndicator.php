<?php

namespace App\Livewire\HumanResources;

use App\Filament\Pages\FactorDeHoras;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Tenant;
use Filament\Facades\Filament;
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
        $factor = null;

        // Solo para quien entra a esa empresa y ve la nómina: trae sueldos.
        if ($tenant && (auth()->user()?->canAccessTenant($tenant) ?? false)) {
            $this->enterTenant($tenant);
            $factor = FactorDeHoras::dashboardIndicator($tenant);
        }

        return view('filament.pages.partials.indicador-factor', ['factor' => $factor]);
    }

    /**
     * La petición con que el navegador pide la pieza después de la página no pasa por
     * todo el arranque del panel: llega sin el panel, sin la empresa y sin el equipo de
     * permisos. Sin ellos el permiso de la nómina no se puede leer y el enlace al detalle
     * no se puede armar —eso daba error 500 en producción—. Se ponen aquí, ya
     * comprobado que el usuario entra a esa empresa.
     */
    private function enterTenant(Tenant $tenant): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($tenant, isQuiet: true);
        CurrentTenant::set($tenant);
        setPermissionsTeamId($tenant->getKey());
    }
}
