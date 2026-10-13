<?php

namespace App\Filament\Widgets\HumanResources;

use App\Domain\HumanResources\Services\HumanResourcesDashboard;
use App\Filament\Pages\TableroTalentoHumano;
use App\Models\Tenant;
use App\Support\FrondaPalette;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Horas extras por grupo en los últimos seis cortes: Producción, Apoyo a mantenimiento,
 * Mantenimiento, MOPE. Es la tendencia del indicador «factor de horas», en horas.
 */
class OvertimeByGroupChart extends ChartWidget
{
    /** Verde y petróleo de la marca, y dos neutros para los grupos menores. */
    private const COLORS = [
        FrondaPalette::Brand[600],
        FrondaPalette::Petrol[400],
        FrondaPalette::Petrol[600],
        FrondaPalette::Brand[300],
        '#94a3b8',
        '#cbd5e1',
    ];

    protected ?string $heading = 'Horas extras por grupo';

    protected ?string $description = 'Los últimos seis cortes de horas, del 27 al 26.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return TableroTalentoHumano::canAccess();
    }

    protected function getData(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $data = app(HumanResourcesDashboard::class)->overtimeByGroup($tenant);
        $datasets = [];

        foreach (array_values(array_keys($data['groups'])) as $i => $label) {
            $datasets[] = [
                'label' => $label,
                'data' => $data['groups'][$label],
                'backgroundColor' => self::COLORS[$i % count(self::COLORS)],
                'borderRadius' => 3,
            ];
        }

        return ['datasets' => $datasets, 'labels' => $data['labels']];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'title' => ['display' => true, 'text' => 'Horas']],
            ],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
