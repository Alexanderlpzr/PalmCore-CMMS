<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Enums\ReportType;
use App\Jobs\GenerateInventoryReportJob;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportManager
{
    public function __construct(
        private readonly WorkOrderPdfService $workOrder,
        private readonly EquipmentPdfService $equipment,
        private readonly MaintenancePlanPdfService $maintenancePlan,
        private readonly InventoryPdfService $inventory,
        private readonly ReliabilityPdfService $reliability,
    ) {}

    public function streamDownload(ReportRequest $request): StreamedResponse
    {
        $service = $this->resolveService($request->type);
        $bytes = $service->generate($request->tenantId, $request->recordId);
        $filename = $service->filename($request->tenantId, $request->recordId);

        return response()->streamDownload(
            fn () => print ($bytes),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function dispatchInventoryReport(ReportRequest $request): void
    {
        GenerateInventoryReportJob::dispatch($request);
    }

    public static function cleanupOldReports(): void
    {
        $disk = Storage::disk('reports');
        $cutoff = now()->subDays(7)->timestamp;

        collect($disk->allFiles())->each(function (string $path) use ($disk, $cutoff): void {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        });
    }

    private function resolveService(ReportType $type): PdfReport
    {
        return match ($type) {
            ReportType::WorkOrder => $this->workOrder,
            ReportType::EquipmentSheet => $this->equipment,
            ReportType::MaintenancePlan => $this->maintenancePlan,
            ReportType::Inventory => $this->inventory,
            ReportType::Reliability => $this->reliability,
        };
    }
}
