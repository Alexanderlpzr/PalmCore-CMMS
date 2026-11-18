<?php

namespace App\Domain\Reports\Services;

use App\Domain\Commercial\Services\MonthlyManagementReport;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * El informe mensual de gerencia en PDF: la cabecera de los informes de la casa —logo,
 * número de documento, QR de verificación— y los datos de {@see MonthlyManagementReport}.
 * No es por planta como los de Indicadores: lo comercial es de la empresa.
 */
class ManagementReportPdfService
{
    public function __construct(
        private readonly ReportBrandingService $branding,
        private readonly MonthlyManagementReport $report,
    ) {}

    public function generate(Tenant $tenant, int $year, int $month): string
    {
        $documentNumber = $this->branding->generateDocumentNumber('IGM');

        return Pdf::loadView('reports.informe-gerencia', [
            ...$this->report->build((string) $tenant->getKey(), $year, $month),
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64($this->branding->documentIdentityPayload($documentNumber, $tenant)),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(int $year, int $month): string
    {
        return sprintf('INFORME-GERENCIA-%04d-%02d.pdf', $year, $month);
    }
}
