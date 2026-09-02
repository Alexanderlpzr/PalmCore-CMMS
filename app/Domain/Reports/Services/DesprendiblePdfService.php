<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Models\PayrollEntry;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * El desprendible de pago de un trabajador.
 *
 * Toda la información sale del renglón guardado y ninguna se recalcula: el nombre, el
 * cargo, el salario y las cifras están congelados en `hr_payroll_entries` desde que se
 * liquidó. Un comprobante que se reconstruye al imprimirlo no es un comprobante, es una
 * opinión de hoy sobre lo que se pagó entonces, y basta con que alguien cambie un
 * parámetro para que el papel de agosto empiece a decir otra cosa.
 *
 * La hoja del libro de Excel hace justo lo contrario: veinticinco `VLOOKUP` que traen los
 * valores en el momento de imprimir, con el número de columna cableado. Insertar una
 * columna en la hoja de liquidación desplaza en silencio todos los conceptos del
 * desprendible.
 */
class DesprendiblePdfService implements PdfReport
{
    public function __construct(private readonly ReportBrandingService $branding) {}

    public function generate(string $tenantId, ?string $recordId = null): string
    {
        $entry = PayrollEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->with(['payrollRun', 'employee'])
            ->firstOrFail();

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        $documentNumber = sprintf(
            'DES-%s-%s',
            $entry->payrollRun->period_start->format('Ym'),
            $entry->document_number,
        );

        return Pdf::loadView('reports.desprendible', [
            'entry' => $entry,
            'run' => $entry->payrollRun,
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64(
                $this->branding->documentIdentityPayload($documentNumber, $tenant),
            ),
            'generatedAt' => now(),
            'earnings' => $this->earningLines($entry),
            'deductions' => $this->deductionLines($entry),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(string $tenantId, ?string $recordId = null): string
    {
        $entry = PayrollEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->with('payrollRun')
            ->first();

        if (! $entry) {
            return 'desprendible.pdf';
        }

        return sprintf(
            'desprendible-%s-%s.pdf',
            $entry->document_number,
            $entry->payrollRun->period_start->format('Y-m'),
        );
    }

    /**
     * Las líneas de devengado, en el orden en que se leen.
     *
     * Solo lo que tiene valor: un desprendible con quince renglones en cero hace más
     * difícil ver los tres que importan.
     *
     * @return array<int, array{concept: string, detail: string, amount: float}>
     */
    private function earningLines(PayrollEntry $entry): array
    {
        $lines = [[
            'concept' => 'Sueldo',
            'detail' => $this->days($entry->worked_days).' días × '.$this->money((float) $entry->day_value),
            'amount' => (float) $entry->worked_days * (float) $entry->day_value,
        ]];

        foreach ($entry->surchargeLines() as $line) {
            $lines[] = [
                'concept' => $line['concept'],
                'detail' => $this->hours($line['hours']).' h × '.$this->money($line['rate']),
                'amount' => $line['amount'],
            ];
        }

        foreach ($entry->novelty_breakdown ?? [] as $row) {
            if (($row['amount'] ?? 0) <= 0) {
                continue;
            }

            $lines[] = [
                'concept' => $row['label'],
                'detail' => $this->days((float) $row['days']).' días',
                'amount' => (float) $row['amount'],
            ];
        }

        foreach ([
            'bonus_housing' => 'Bonificación por vivienda',
            'bonus_constitutive' => 'Bonificación constitutiva',
            'bonus_non_constitutive' => 'Bonificación no constitutiva',
            'transport_allowance' => 'Auxilio de transporte',
        ] as $field => $label) {
            $amount = (float) $entry->{$field};

            if ($amount > 0) {
                $lines[] = ['concept' => $label, 'detail' => '', 'amount' => $amount];
            }
        }

        return $lines;
    }

    /**
     * @return array<int, array{concept: string, detail: string, amount: float}>
     */
    private function deductionLines(PayrollEntry $entry): array
    {
        $lines = [];

        foreach ([
            'health_deduction' => 'Aporte a salud',
            'pension_deduction' => 'Aporte a pensión',
            'solidarity_fund' => 'Fondo de solidaridad pensional',
            'withholding_tax' => 'Retención en la fuente',
        ] as $field => $label) {
            $amount = (float) $entry->{$field};

            if ($amount > 0) {
                $lines[] = ['concept' => $label, 'detail' => '', 'amount' => $amount];
            }
        }

        foreach ($entry->other_deductions_breakdown ?? [] as $row) {
            $lines[] = [
                'concept' => $row['concept'] ?? 'Otro descuento',
                'detail' => '',
                'amount' => (float) ($row['amount'] ?? 0),
            ];
        }

        return $lines;
    }

    private function money(float $value): string
    {
        return '$ '.number_format($value, 0, ',', '.');
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function days(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
