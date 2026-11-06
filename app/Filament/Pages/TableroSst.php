<?php

namespace App\Filament\Pages;

use App\Domain\Safety\Services\SafetyFindingsDashboard;
use App\Filament\Resources\Safety\SstFindingResource;
use App\Models\SstFinding;
use App\Models\Tenant;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * El tablero de SST: lo primero que ven las cuentas de SST y de proceso al entrar.
 * Cuántos hallazgos siguen abiertos, cuáles se vencieron, cómo va cada área responsable
 * y en qué secciones se concentran. Cada tarjeta abre la lista ya filtrada.
 */
class TableroSst extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Seguridad y Salud en el Trabajo';

    protected static ?string $navigationLabel = 'Tablero';

    protected static ?string $title = 'Seguridad y Salud en el Trabajo';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'sst';

    protected string $view = 'filament.pages.tablero-sst';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', SstFinding::class) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Hallazgos al '.CarbonImmutable::now($this->tenant()->plantTimezone())->locale('es')->translatedFormat('j \d\e F \d\e Y').'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reportar')
                ->label('Reportar hallazgo')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->url(fn (): string => SstFindingResource::getUrl('create'))
                ->visible(fn (): bool => SstFindingResource::canCreate()),
        ];
    }

    protected function getViewData(): array
    {
        $today = SstFinding::today((string) $this->tenant()->getKey());
        $summary = app(SafetyFindingsDashboard::class)->summary((string) $this->tenant()->getKey(), $today);
        $list = fn (string $tab): string => SstFindingResource::getUrl('index', ['tab' => $tab]);

        return [
            'summary' => $summary,
            'cards' => [
                ['label' => 'Pendientes', 'value' => $summary['pendiente'], 'detail' => 'Ningún área ha empezado', 'icon' => 'heroicon-o-clock', 'tone' => $summary['pendiente'] > 0 ? 'warning' : 'ok', 'url' => $list('abiertos')],
                ['label' => 'En ejecución', 'value' => $summary['enEjecucion'], 'detail' => 'Alguna área ya está corrigiendo', 'icon' => 'heroicon-o-wrench-screwdriver', 'tone' => 'neutral', 'url' => $list('abiertos')],
                ['label' => 'Vencidos', 'value' => $summary['vencidos'], 'detail' => 'Abiertos y fuera de plazo', 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => $summary['vencidos'] > 0 ? 'danger' : 'ok', 'url' => $list('vencidos')],
                ['label' => 'Por verificar', 'value' => $summary['porVerificar'], 'detail' => 'Corregidos, falta la revisión de SST', 'icon' => 'heroicon-o-eye', 'tone' => $summary['porVerificar'] > 0 ? 'warning' : 'ok', 'url' => $list('por-verificar')],
                ['label' => 'Verificados', 'value' => $summary['verificados'], 'detail' => 'Cerrados por SST', 'icon' => 'heroicon-o-shield-check', 'tone' => 'ok', 'url' => $list('todos')],
            ],
            'findingUrl' => fn (SstFinding $finding): string => SstFindingResource::getUrl('view', ['record' => $finding]),
            'today' => $today,
        ];
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }
}
