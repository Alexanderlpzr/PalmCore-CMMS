<?php

namespace App\Filament\Widgets\HumanResources;

use App\Domain\HumanResources\Services\HumanResourcesDashboard;
use App\Filament\Pages\TableroTalentoHumano;
use App\Filament\Widgets\Concerns\DespliegaDetalle;
use App\Filament\Widgets\Concerns\DiceCuandoNoHayDatos;
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
    use DespliegaDetalle;
    use DiceCuandoNoHayDatos;

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

    /** Tocar un corte abre su ficha: las horas extra de cada grupo y el total (2026-11-19). */
    protected string $view = 'filament.widgets.grafica-con-detalle';

    public function detalles(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $data = app(HumanResourcesDashboard::class)->overtimeByGroup($tenant);
        $horas = fn (float $valor): string => number_format($valor, 1, ',', '.').' h';

        return array_map(function (string $label, int $index) use ($data, $horas): array {
            $cifras = [];
            $total = 0.0;

            foreach ($data['groups'] as $group => $values) {
                $value = (float) ($values[$index] ?? 0);
                $total += $value;
                $cifras[$group] = $horas($value);
            }

            return self::ficha('Corte '.mb_strtolower($label), ['Total' => $horas($total), ...$cifras]);
        }, $data['labels'], array_keys($data['labels']));
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
