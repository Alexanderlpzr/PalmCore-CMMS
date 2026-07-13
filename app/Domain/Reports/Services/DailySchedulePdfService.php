<?php

namespace App\Domain\Reports\Services;

use App\Domain\Maintenance\Services\DailyScheduleService;
use App\Domain\Reports\Contracts\PdfReport;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * El programa del día, impreso.
 *
 * Deliberately does not implement {@see PdfReport}:
 * that contract identifies a report by a single `recordId`, and this one is keyed
 * by a *date* (and optionally a plant). Squeezing a date into a parameter called
 * `recordId` would be a lie the next reader has to unpick.
 */
class DailySchedulePdfService
{
    public function __construct(
        private readonly DailyScheduleService $schedule,
        private readonly ReportBrandingService $branding,
    ) {}

    public function generate(string $tenantId, CarbonInterface|string|null $day = null, ?string $plantId = null): string
    {
        $day = $day !== null ? Carbon::parse($day)->startOfDay() : now()->startOfDay();

        $schedule = $this->schedule->forDay($tenantId, $day, $plantId);

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $documentNumber = $this->branding->generateDocumentNumber('PROG-DIA');

        return Pdf::loadView('reports.daily-schedule', [
            ...$schedule,
            'schedule' => $this->schedule,
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64($this->branding->documentIdentityPayload($documentNumber, $tenant)),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(CarbonInterface|string|null $day = null): string
    {
        $day = $day !== null ? Carbon::parse($day) : now();

        return 'PROGRAMA-'.$day->format('Y-m-d').'.pdf';
    }
}
