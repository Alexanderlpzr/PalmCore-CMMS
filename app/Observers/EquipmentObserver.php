<?php

namespace App\Observers;

use App\Jobs\GenerateEquipmentQrCode;
use App\Models\Equipment;
use Illuminate\Support\Facades\Storage;

class EquipmentObserver
{
    /** Auto-generate QR after equipment creation (async, non-blocking). */
    public function created(Equipment $equipment): void
    {
        GenerateEquipmentQrCode::dispatch($equipment)->afterResponse();
    }

    /** Clean up QR image when equipment is permanently deleted. */
    public function forceDeleted(Equipment $equipment): void
    {
        $qrCode = $equipment->qrCode()->withTrashed()->first();

        if ($qrCode?->qr_image_path) {
            Storage::disk('public')->delete($qrCode->qr_image_path);
        }
    }
}
