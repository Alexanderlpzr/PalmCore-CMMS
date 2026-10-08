<?php

namespace App\Domain\Reports\Excel;

use App\Domain\Reports\Services\DesprendiblePdfService;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los desprendibles de toda la nómina en un solo Excel: una pestaña «Resumen» con todos
 * los trabajadores —una fila por persona y una columna por concepto— y después una pestaña
 * por trabajador con su comprobante, con las horas extras y los recargos línea por línea.
 *
 * Las cifras salen del renglón guardado, como el desprendible en PDF, y las líneas son las
 * mismas: el papel y el Excel no pueden decir cosas distintas.
 */
class DesprendiblesExcel
{
    public function __construct(private readonly DesprendiblePdfService $desprendible) {}

    public function download(PayrollRun $run): StreamedResponse
    {
        return $this->build($run)->download($this->filename($run));
    }

    public function filename(PayrollRun $run): string
    {
        return 'DESPRENDIBLES-'.$run->period_start->format('Y-m').'.xlsx';
    }

    public function build(PayrollRun $run): FrondaWorkbook
    {
        $entries = $run->entries()->orderBy('employee_name')->get();
        $company = Tenant::withoutGlobalScopes()->find($run->tenant_id)?->name;
        $period = 'Del '.$run->period_start->format('d/m/Y').' al '.$run->period_end->format('d/m/Y')
            .($run->hasHoursCutoff() ? ' · horas del '.$run->hoursFrom()->format('d/m/Y').' al '.$run->hoursTo()->format('d/m/Y') : '');

        $book = FrondaWorkbook::create();
        $this->summary($book, $entries, $run, $company, $period);

        foreach ($entries as $entry) {
            $this->slip($book, $entry, $run, $company, $period);
        }

        return $book;
    }

    /** @param  Collection<int, PayrollEntry>  $entries */
    private function summary(FrondaWorkbook $book, $entries, PayrollRun $run, ?string $company, string $period): void
    {
        $columns = [
            'Trabajador' => fn (PayrollEntry $e) => $e->employee_name,
            'Cédula' => fn (PayrollEntry $e) => $e->document_number,
            'Cargo' => fn (PayrollEntry $e) => $e->position,
            'Días' => fn (PayrollEntry $e) => (float) $e->worked_days,
            'Básico devengado' => fn (PayrollEntry $e) => (float) $e->basic_earned,
            'Recargos y extras' => fn (PayrollEntry $e) => (float) $e->surcharges_total,
            'Bonificación por horas' => fn (PayrollEntry $e) => (float) $e->hours_bonus_total,
            'Bonificaciones' => fn (PayrollEntry $e) => (float) $e->bonuses_total,
            'Auxilio de transporte' => fn (PayrollEntry $e) => (float) $e->transport_allowance,
            'Devengado' => fn (PayrollEntry $e) => (float) $e->total_earned,
            'Salud' => fn (PayrollEntry $e) => (float) $e->health_deduction,
            'Pensión' => fn (PayrollEntry $e) => (float) $e->pension_deduction,
            'Otras deducciones' => fn (PayrollEntry $e) => (float) $e->other_deductions,
            'Deducido' => fn (PayrollEntry $e) => (float) $e->total_deducted,
            'Neto a pagar' => fn (PayrollEntry $e) => (float) $e->net_pay,
        ];

        $headers = array_keys($columns);
        $formats = [4 => FrondaWorkbook::HOURS] + array_fill(5, count($headers) - 4, FrondaWorkbook::MONEY);
        $rows = $entries->map(fn (PayrollEntry $e): array => array_map(fn (callable $get) => $get($e), array_values($columns)));

        $totals = ['Total ('.$entries->count().' trabajadores)', '', '', ''];

        for ($i = 4; $i < count($headers); $i++) {
            $totals[] = $rows->sum($i);
        }

        $book->sheet('Resumen')
            ->widths([1 => 30, 2 => 14, 3 => 22, 4 => 8] + array_fill(5, count($headers) - 4, 15))
            ->banner('Nómina · '.$run->name, $period, $company, count($headers))
            ->table($headers, $rows, $formats)
            ->totals($totals, $formats)
            ->footer();
    }

    private function slip(FrondaWorkbook $book, PayrollEntry $entry, PayrollRun $run, ?string $company, string $period): void
    {
        $label = fn (): Style => $book->style(bold: true, fill: FrondaWorkbook::MIST, color: FrondaWorkbook::PETROL);
        $section = fn (): Style => $book->style(bold: true, fill: FrondaWorkbook::PETROL, color: 'FFFFFF', size: 10);
        $money = [3 => FrondaWorkbook::MONEY];

        $book->sheet($entry->employee_name)
            ->widths([1 => 34, 2 => 34, 3 => 18])
            ->banner('Comprobante de pago de nómina', $run->name.' · '.$period, $company, 3);

        foreach ([
            ['Trabajador', $entry->employee_name],
            ['Cédula', $entry->document_number],
            ['Cargo', $entry->position ?? '—'],
            ['Salario básico mensual', (float) $entry->base_salary],
            ['Días trabajados · de novedad', rtrim(rtrim(number_format((float) $entry->worked_days, 2, ',', '.'), '0'), ',').' · '.rtrim(rtrim(number_format((float) $entry->novelty_days, 2, ',', '.'), '0'), ',')],
        ] as [$name, $value]) {
            $book->merged([$name, $value, ''], 2, 3, fn (int $c): Style => $c === 1 ? $label() : $book->style(format: is_float($value) ? FrondaWorkbook::MONEY : null, align: CellAlignment::LEFT));
        }

        $book->blank()->merged(['DEVENGADO', '', ''], 1, 3, $section);
        $book->table(['Concepto', 'Detalle', 'Valor'], array_map(fn (array $l): array => [$l['concept'], $l['detail'], (float) $l['amount']], $this->desprendible->earningLines($entry)), $money);
        $book->totals(['Total devengado', '', (float) $entry->total_earned], $money);

        $book->blank()->merged(['DEDUCCIONES', '', ''], 1, 3, $section);
        $deductions = $this->desprendible->deductionLines($entry);
        $book->table(['Concepto', 'Detalle', 'Valor'], $deductions ? array_map(fn (array $l): array => [$l['concept'], $l['detail'], (float) $l['amount']], $deductions) : [['Sin deducciones en el período.', '', 0.0]], $money);
        $book->totals(['Total deducido', '', (float) $entry->total_deducted], $money);

        $book->blank()->row(['NETO A PAGAR', '', (float) $entry->net_pay], fn (int $c): Style => $book->style(bold: true, fill: FrondaWorkbook::GREEN, color: 'FFFFFF', size: 13, align: $c === 3 ? CellAlignment::RIGHT : CellAlignment::LEFT, format: $c === 3 ? FrondaWorkbook::MONEY : null));

        // Las bases: lo que se aporta y lo que se provisiona.
        $book->blank()->merged(['BASES DE APORTE Y PRESTACIONES', '', ''], 1, 3, $section);
        $book->table(['Base', '', 'Valor'], [
            ['IBC de salud', '', (float) $entry->ibc_health],
            ['IBC de pensión', '', (float) $entry->ibc_pension],
            ['Base de prima y cesantías', '', (float) $entry->severance_base],
            ['Base de vacaciones', '', (float) $entry->vacation_base],
        ], $money);

        if ($entry->hasWarnings()) {
            $book->blank()->merged(['Avisos: '.implode(' · ', $entry->warnings), '', ''], 1, 3, fn (): Style => $book->style(fill: 'FDF3E2', color: '9A5B06'));
        }

        $book->footer();
    }
}
