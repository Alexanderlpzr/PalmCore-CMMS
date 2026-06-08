<?php

namespace App\Http\Controllers;

use App\Models\EquipmentQrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class EquipmentPublicController extends Controller
{
    /**
     * Public QR landing page — no authentication required.
     * Token is the only access control: UUID v4, 122 bits of entropy.
     */
    public function show(string $token): View|Response
    {
        $qrCode = EquipmentQrCode::withoutGlobalScopes()
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $qrCode) {
            return response(view('equipment.qr-not-found'), 404);
        }

        // Record the scan asynchronously (fire-and-forget)
        $qrCode->recordScan();

        $equipment = $qrCode->equipment()
            ->withoutGlobalScopes()
            ->with(['plant', 'area', 'category', 'primaryPhoto'])
            ->first();

        return view('equipment.public-profile', compact('equipment', 'qrCode'));
    }
}
