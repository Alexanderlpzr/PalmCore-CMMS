<?php

namespace App\Domain\Reports\Services;

use App\Models\Tenant;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared branding elements for the five PDF report templates: tenant logo,
 * a scannable QR, and the document number/version pair shown in every header.
 * Centralized so all reports look like one product.
 */
class ReportBrandingService
{
    /** Fixed for now — there is no document revision system to derive this from. */
    public const DOCUMENT_VERSION = '1.0';

    public function logoBase64(?Tenant $tenant): ?string
    {
        if ($tenant?->logo_path) {
            try {
                $content = Storage::disk(persistent_disk())->get($tenant->logo_path);
                $mime = Storage::disk(persistent_disk())->mimeType($tenant->logo_path);

                return "data:{$mime};base64,".base64_encode($content);
            } catch (\Throwable) {
                // Fall through to the Fronda CMMS default below.
            }
        }

        return $this->frondaLogoBase64();
    }

    /**
     * Every report carries Fronda CMMS's own brand identity by default —
     * tenants without a custom logo still get a properly branded document
     * instead of a bare text fallback.
     */
    private function frondaLogoBase64(): ?string
    {
        $path = public_path('images/logo.png');

        if (! is_file($path)) {
            return null;
        }

        try {
            return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generates a unique document number when the report has no natural one
     * of its own (e.g. aggregate reports like Inventory or Reliability, which
     * don't correspond to a single record).
     */
    public function generateDocumentNumber(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), strtoupper(Str::random(5)));
    }

    /**
     * Plain-text identity payload for reports that have no single record to
     * deep-link to (Inventory, Reliability — aggregate snapshots, not one row).
     */
    public function documentIdentityPayload(string $documentNumber, ?Tenant $tenant): string
    {
        return implode("\n", array_filter([
            'Fronda CMMS',
            $tenant?->name,
            "Doc: {$documentNumber}",
            'Emitido: '.now()->format('d/m/Y H:i'),
        ]));
    }

    /**
     * Deep-links the QR to the record's authenticated Filament page — reusing
     * the panel's existing tenant-scoped route, not a new one. The URL itself
     * carries no secret: it's a tenant slug + record UUID, the same shape
     * already used throughout the app's own links. Scanning it without a
     * valid session just lands on the login screen, same as pasting the URL
     * manually. No new route, controller, or public endpoint was introduced.
     */
    public function recordUrl(string $routeName, Tenant $tenant, string $recordId): ?string
    {
        try {
            return route($routeName, ['tenant' => $tenant->slug, 'record' => $recordId]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Renders a QR as base64 PNG, embedded inline since DomPDF cannot fetch
     * external or signed URLs at render time.
     */
    public function qrBase64(string $payload): string
    {
        $options = new QROptions;
        $options->outputType = QROutputInterface::GDIMAGE_PNG;
        $options->eccLevel = EccLevel::M;
        $options->scale = 6;
        $options->outputBase64 = false;
        $options->addQuietzone = true;
        $options->quietzoneSize = 2;

        $png = (new QRCode($options))->render($payload);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
