<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Domain\Shared\Enums\ActivityType;
use App\Models\ActivityLocation;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class WorkOrderPdfService implements PdfReport
{
    public function __construct(private readonly ReportBrandingService $branding) {}

    public function generate(string $tenantId, ?string $recordId = null): string
    {
        $workOrder = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->with([
                'equipment.plant',
                'equipment.area',
                'createdBy',
                'assignedSupervisor',
                'technicians.user',
                'timeLogs.user',
                'parts.sparePart',
                'comments.user',
                'signatures.user',
            ])
            ->firstOrFail();

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $documentNumber = $workOrder->work_order_number;
        $signatureLocations = $this->signatureLocations($tenantId, $workOrder->signatures);

        $qrTarget = $this->branding->recordUrl(
            'filament.admin.resources.maintenance.work-order.work-orders.view',
            $tenant,
            $workOrder->id,
        ) ?? $this->branding->documentIdentityPayload($documentNumber, $tenant);

        return Pdf::loadView('reports.work-order', [
            'workOrder' => $workOrder,
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'signatureImages' => $this->signatureImages($workOrder->signatures),
            'signatureLocations' => $signatureLocations,
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64($qrTarget),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(string $tenantId, ?string $recordId = null): string
    {
        $number = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->value('work_order_number') ?? $recordId;

        return 'OT-'.str_replace('/', '-', (string) $number).'-'.now()->format('Ymd').'.pdf';
    }

    /**
     * DomPDF cannot reach signed/private URLs at render time, so each signature
     * image is embedded as base64 — the same approach used for the tenant logo.
     *
     * @return array<string, string> keyed by signature id
     */
    private function signatureImages(iterable $signatures): array
    {
        $images = [];

        foreach ($signatures as $signature) {
            if (! $signature->image_path) {
                continue;
            }

            try {
                $content = Storage::disk(private_files_disk())->get($signature->image_path);
                $mime = Storage::disk(private_files_disk())->mimeType($signature->image_path);

                if (! $content || ! $mime) {
                    continue;
                }

                $images[$signature->id] = "data:{$mime};base64,".base64_encode($content);
            } catch (\Throwable) {
                continue;
            }
        }

        return $images;
    }

    /**
     * One query for all of the work order's signatures — avoids N+1 when a WO
     * has both a technician and a supervisor signature.
     *
     * @return array<string, ActivityLocation> keyed by signature id
     */
    private function signatureLocations(string $tenantId, iterable $signatures): array
    {
        $ids = collect($signatures)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        return ActivityLocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('activity_type', ActivityType::Signature)
            ->whereIn('activity_id', $ids)
            ->get()
            ->keyBy('activity_id')
            ->all();
    }
}
