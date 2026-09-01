<?php

namespace App\Domain\Reports\Services;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Domain\Reports\Contracts\PeriodReport;
use App\Models\Plant;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;

/**
 * Lo que los cuatro informes de Indicadores tienen en común.
 *
 * La cabecera de un informe de esta casa lleva siempre lo mismo —logo del tenant, número
 * de documento, versión, QR de verificación y fecha de emisión— y armarlo son ocho líneas
 * idénticas. Los ocho informes que ya existen las repiten uno por uno, y ahí se nota:
 * cada plantilla las fue copiando y es lo que dejó que se separaran entre sí.
 *
 * Aquí se escriben una vez. Cada informe pone solo sus datos y el nombre de su vista.
 */
abstract class PeriodPdfReport implements PeriodReport
{
    public function __construct(protected readonly ReportBrandingService $branding) {}

    /** Las siglas del número de documento: PNP, ENE, PRD… */
    abstract protected function documentPrefix(): string;

    /** La vista Blade que pinta este informe. */
    abstract protected function view(): string;

    /**
     * Los datos propios del informe, sobre la ventana pedida.
     *
     * @return array<string, mixed>
     */
    abstract protected function data(Plant $plant, CarbonInterface $from, CarbonInterface $to): array;

    public function generate(Plant $plant, CarbonInterface $from, CarbonInterface $to): string
    {
        return Pdf::loadView($this->view(), [
            ...$this->branding($plant, $from, $to),
            ...$this->data($plant, $from, $to),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(Plant $plant, CarbonInterface $from, CarbonInterface $to): string
    {
        $periodo = $from->format('Y-m') === $to->format('Y-m')
            ? $from->format('Y-m')
            : $from->format('Y-m').'_'.$to->format('Y-m');

        return $this->documentPrefix().'-'.$periodo.'.pdf';
    }

    /**
     * La identidad del documento: quién lo emite, cuándo, y con qué número se verifica.
     *
     * @return array<string, mixed>
     */
    protected function branding(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $tenant = Tenant::withoutGlobalScopes()->find($plant->tenant_id);
        $documentNumber = $this->branding->generateDocumentNumber($this->documentPrefix());

        return [
            'plant' => $plant,
            'from' => $from,
            'to' => $to,
            'periodLabel' => self::periodLabel($from, $to),
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64(
                $this->branding->documentIdentityPayload($documentNumber, $tenant),
            ),
            'generatedAt' => now(),
        ];
    }

    /**
     * El período, dicho como lo diría una persona.
     *
     * Un mes se dice como un mes: «Agosto de 2026», no «Agosto – Agosto de 2026», que es
     * una forma rara de escribir lo mismo. Y el año solo se repite cuando el rango lo
     * cruza. La misma regla que sigue {@see DashboardPeriod::label()},
     * que aquí no se puede reutilizar porque aquella parte de los filtros de la pantalla y
     * esto parte de dos fechas ya resueltas.
     */
    public static function periodLabel(CarbonInterface $from, CarbonInterface $to): string
    {
        if ($from->format('Y-m') === $to->format('Y-m')) {
            return ucfirst($from->translatedFormat('F \d\e Y'));
        }

        if ($from->year === $to->year) {
            return ucfirst($from->translatedFormat('F')).' – '.$to->translatedFormat('F \d\e Y');
        }

        return ucfirst($from->translatedFormat('F \d\e Y')).' – '.$to->translatedFormat('F \d\e Y');
    }
}
