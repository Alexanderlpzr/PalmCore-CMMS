<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Services\CmmsDataAuditService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Auditoría de integridad de datos: corre en vivo, sobre la empresa actual, los
 * chequeos de CmmsDataAuditService y los pinta como tarjetas. Es la herramienta
 * para «hacer una auditoría» sin salir del panel ni consultar la base a mano.
 */
class DataAudit extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Auditoría de Datos';

    protected static ?string $title = 'Auditoría de Datos';

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.data-audit';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_super_admin || $user?->can('maintenance-plans.view'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $findings = app(CmmsDataAuditService::class)->run(Filament::getTenant()->id);

        return [
            'findings' => $findings,
            'criticalCount' => collect($findings)->where('severity.value', 'danger')->count(),
        ];
    }
}
