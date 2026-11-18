<?php

namespace App\Domain\Reports\Excel;

use App\Domain\Commercial\Services\MonthlyManagementReport;
use App\Models\Tenant;
use OpenSpout\Common\Entity\Style\CellAlignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El informe mensual de gerencia en Excel: las mismas cifras del PDF, una hoja por
 * sección y con números de verdad (no texto), para que la Directora pueda sumar, filtrar
 * o pegarlas en su presentación.
 */
final class ManagementReportExcel
{
    private const TONS = '#,##0.00';

    private const PERCENT = '0.0%';

    public function __construct(private MonthlyManagementReport $report) {}

    public function build(Tenant $tenant, int $year, int $month): FrondaWorkbook
    {
        $data = $this->report->build((string) $tenant->getKey(), $year, $month);
        $s = $data['summary'];
        $m = $data['monthName'];
        $book = FrondaWorkbook::create($tenant);
        $banner = fn (string $title, int $width) => $book->banner("Informe de gerencia · {$title}", $data['periodLabel'].' · contra: '.$data['planLabel'], $tenant->name, $width);

        $book->sheet('Resumen')->widths([1 => 46, 2 => 18, 3 => 18]);
        $banner('Resumen', 3);
        // Cada fila con su formato —toneladas, porcentaje o pesos—, que table() pone por columna.
        $book->table(['Indicador', "En {$m}", 'En el año'], []);
        $rows = [
            ['Aceite proyectado (tn)', $s['oil_month_projected'], $s['oil_ytd_projected'], self::TONS],
            ['Aceite despachado (tn)', $s['oil_month_dispatched'], $s['oil_ytd_dispatched'], self::TONS],
            ['Cumplimiento del aceite', $s['oil_month_ratio'], $s['oil_ytd_ratio'], self::PERCENT],
            ['Fruta proyectada (tn)', $s['fruit_month_projected'], $s['fruit_ytd_projected'], self::TONS],
            ['Fruta recibida (tn)', $s['fruit_month_received'], $s['fruit_ytd_received'], self::TONS],
            ['Cumplimiento de la fruta', $s['fruit_month_ratio'], $s['fruit_ytd_ratio'], self::PERCENT],
            ['Aceite nacional despachado (% del aceite)', $data['mix']['months'][$month]['real_share'], $data['mix']['year']['real_share'], self::PERCENT],
            ['Aceite nacional proyectado (% del aceite)', $data['mix']['months'][$month]['plan_share'], $data['mix']['year']['plan_share'], self::PERCENT],
            ['Palmiste despachado (tn)', $s['pko_month_dispatched'], $s['pko_ytd_dispatched'], self::TONS],
            ['Facturado', $s['invoiced_month'], $s['invoiced_ytd'], FrondaWorkbook::MONEY],
            ['Precio del aceite por tonelada', $s['oil_price_month'], null, FrondaWorkbook::MONEY],
            ['Cartera por cobrar hoy', null, $s['receivable'], FrondaWorkbook::MONEY],
            ['Cartera vencida hoy', null, $s['overdue'], FrondaWorkbook::MONEY],
        ];

        foreach ($rows as $n => [$label, $month_, $year_, $format]) {
            $book->row([$label, $month_, $year_], fn (int $c) => $book->style(
                fill: $n % 2 === 1 ? FrondaWorkbook::MIST : null,
                align: $c === 1 ? CellAlignment::LEFT : CellAlignment::RIGHT,
                format: $c === 1 ? null : $format,
            ));
        }

        $book->footer();

        $book->sheet('Aceite por cliente')->widths([1 => 30, 2 => 16, 3 => 16, 4 => 10, 5 => 16, 6 => 16, 7 => 10]);
        $banner('Aceite por cliente', 7);
        $formats = [2 => self::TONS, 3 => self::TONS, 4 => self::PERCENT, 5 => self::TONS, 6 => self::TONS, 7 => self::PERCENT];
        $book->table(['Cliente', "Proyectado {$m} (tn)", "Despachado {$m} (tn)", '% mes', 'Proyectado año (tn)', 'Despachado año (tn)', '% año'],
            array_map(fn (array $r): array => [$r['name'], $r['month_projected'], $r['month_dispatched'], $r['month_ratio'], $r['ytd_projected'], $r['ytd_dispatched'], $r['ytd_ratio']], array_values($data['products']['cpo']['rows'])),
            $formats,
        );
        $book->totals(['Total', $data['products']['cpo']['month_projected'], $data['products']['cpo']['month_dispatched'], $s['oil_month_ratio'], $s['oil_ytd_projected'], $s['oil_ytd_dispatched'], $s['oil_ytd_ratio']], $formats);
        $book->footer();

        $book->sheet('Facturación')->widths([1 => 30, 2 => 10, 3 => 14, 4 => 18, 5 => 16, 6 => 20]);
        $banner("Facturación de {$m}", 6);
        $formats = [3 => self::TONS, 4 => FrondaWorkbook::MONEY, 5 => FrondaWorkbook::MONEY, 6 => FrondaWorkbook::MONEY];
        $book->table(['Cliente', 'Producto', 'Toneladas', 'Facturado', '$ por tonelada', 'Facturado en el año'],
            array_map(fn (array $r): array => [$r['name'], $r['product'], $r['tons'], $r['amount'], $r['price'], $r['ytd_amount']], $data['invoicing']),
            $formats,
        );
        $book->totals(['Total', '', '', $s['invoiced_month'], $s['oil_price_month'], $s['invoiced_ytd']], $formats);
        $book->footer();

        $book->sheet('Cartera')->widths([1 => 30, 2 => 16, 3 => 16, 4 => 16, 5 => 16, 6 => 16, 7 => 18, 8 => 12, 9 => 14]);
        $banner('Cartera por antigüedad (hoy)', 9);
        $formats = array_fill(2, 6, FrondaWorkbook::MONEY);
        $book->table(['Cliente', 'Al día', '1–30 días', '31–60 días', '61–90 días', 'Más de 90', 'Total', 'Facturas', 'Días de la más vieja'],
            array_map(fn (array $r): array => [$r['name'], $r['current'], $r['d30'], $r['d60'], $r['d90'], $r['over90'], $r['total'], $r['invoices'], $r['oldest_days']], $data['aging']),
            $formats,
        );
        $sum = fn (string $k): float => round(array_sum(array_column($data['aging'], $k)), 2);
        $book->totals(['Total', $sum('current'), $sum('d30'), $sum('d60'), $sum('d90'), $sum('over90'), $sum('total'), array_sum(array_column($data['aging'], 'invoices')), ''], $formats);
        $book->footer();

        $book->sheet('Fruta')->widths([1 => 30, 2 => 16, 3 => 16, 4 => 10, 5 => 16, 6 => 16, 7 => 10]);
        $banner('Fruta por proveedor', 7);
        $formats = [2 => self::TONS, 3 => self::TONS, 4 => self::PERCENT, 5 => self::TONS, 6 => self::TONS, 7 => self::PERCENT];
        $book->table(['Proveedor', "Proyectada {$m} (tn)", "Recibida {$m} (tn)", '% mes', 'Proyectada año (tn)', 'Recibida año (tn)', '% año'],
            array_map(fn (array $r): array => [$r['name'], $r['month_projected'], $r['month_received'], $r['month_ratio'], $r['ytd_projected'], $r['ytd_received'], $r['ytd_ratio']], $data['fruit']),
            $formats,
        );
        $book->totals(['Total', $s['fruit_month_projected'], $s['fruit_month_received'], $s['fruit_month_ratio'], $s['fruit_ytd_projected'], $s['fruit_ytd_received'], $s['fruit_ytd_ratio']], $formats);
        $book->footer();

        $book->sheet('Ofertas abiertas')->widths([1 => 22, 2 => 26, 3 => 10, 4 => 16, 5 => 16, 6 => 14]);
        $banner('Ofertas mercantiles abiertas', 6);
        $book->table(['Oferta', 'Cliente', 'Producto', 'Ofertadas (tn)', 'Despachadas (tn)', 'Saldo (tn)'],
            array_map(fn (array $o): array => [$o['number'], $o['customer'], $o['product'], $o['contracted'], $o['delivered'], $o['remaining']], $data['offers']),
            [4 => self::TONS, 5 => self::TONS, 6 => self::TONS],
        );
        $book->footer();

        return $book;
    }

    public function download(Tenant $tenant, int $year, int $month): StreamedResponse
    {
        return $this->build($tenant, $year, $month)->download(sprintf('INFORME-GERENCIA-%04d-%02d.xlsx', $year, $month));
    }
}
