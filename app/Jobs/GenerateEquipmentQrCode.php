<?php

namespace App\Jobs;

use App\Domain\Assets\Services\QrCodeService;
use App\Models\Equipment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateEquipmentQrCode implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $backoff = 10;

    public function __construct(public readonly Equipment $equipment) {}

    public function handle(QrCodeService $service): void
    {
        // Use withoutGlobalScopes: queue workers have no tenant context (CurrentTenant not set)
        // so BelongsToTenant scope would filter by null and miss the existing record.
        if ($this->equipment->qrCode()->withoutGlobalScopes()->exists()) {
            return;
        }

        $service->createForEquipment($this->equipment);
    }

    public function failed(Throwable $exception): void
    {
        // Log failure — operational team can regenerate via Filament action
        logger()->error('QR generation failed', [
            'equipment_id' => $this->equipment->id,
            'error'        => $exception->getMessage(),
        ]);
    }
}
