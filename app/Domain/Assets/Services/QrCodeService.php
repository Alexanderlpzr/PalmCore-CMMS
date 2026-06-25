<?php

namespace App\Domain\Assets\Services;

use App\Models\Equipment;
use App\Models\EquipmentQrCode;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeService
{
    // ── Token ─────────────────────────────────────────────────────────────────

    /**
     * UUID v4 (random) — intentionally NOT v7 (time-ordered) to prevent token enumeration.
     */
    public function generateToken(): string
    {
        return (string) Str::uuid();
    }

    // ── URL ───────────────────────────────────────────────────────────────────

    public function buildPublicUrl(string $token): string
    {
        return route('equipment.qr.show', ['token' => $token]);
    }

    // ── Image ─────────────────────────────────────────────────────────────────

    /**
     * Generate a PNG QR image via GD (no ext-imagick required) and store it on the public disk.
     * Returns the stored relative path.
     */
    public function generateImage(string $url, string $tenantId, string $token): string
    {
        $options = new QROptions;
        $options->outputType = QROutputInterface::GDIMAGE_PNG;
        $options->eccLevel = EccLevel::H;
        $options->scale = 10;
        $options->outputBase64 = false;
        $options->addQuietzone = true;
        $options->quietzoneSize = 4;

        $pngBinary = (new QRCode($options))->render($url);

        $path = "equipment-qr/{$tenantId}/{$token}.png";

        Storage::disk(persistent_disk())->put($path, $pngBinary);

        return $path;
    }

    public function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk(persistent_disk())->delete($path);
        }
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function createForEquipment(Equipment $equipment): EquipmentQrCode
    {
        $token = $this->generateToken();
        $url = $this->buildPublicUrl($token);
        $path = $this->generateImage($url, $equipment->tenant_id, $token);

        return $equipment->qrCode()->create([
            'tenant_id' => $equipment->tenant_id,
            'qr_token' => $token,
            'qr_image_path' => $path,
            'is_active' => true,
            'generated_at' => now(),
            'scan_count' => 0,
        ]);
    }

    /**
     * Deactivate old token (invalidates printed QRs), create new one.
     * Wrapped in a DB transaction so both operations succeed or neither does.
     */
    public function regenerate(EquipmentQrCode $qrCode): EquipmentQrCode
    {
        return DB::transaction(function () use ($qrCode): EquipmentQrCode {
            $oldImagePath = $qrCode->qr_image_path;

            $qrCode->update(['is_active' => false]);
            $qrCode->delete(); // soft-delete keeps audit trail

            $newQrCode = $this->createForEquipment($qrCode->equipment);

            $this->deleteImage($oldImagePath);

            return $newQrCode;
        });
    }
}
