<?php

namespace App\Domain\HumanResources\Services;

use App\Models\Employee;
use App\Models\EmployeeQrCode;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * El carné QR del trabajador.
 *
 * Mismo mecanismo que el QR de equipos, con una diferencia deliberada: el código no
 * lleva una URL, lleva el token pelado. El QR del equipo apunta a una página pública
 * porque leer la ficha de una bomba es inofensivo; este no debe abrir nada si alguien
 * lo fotografía en la puerta. El token viaja al endpoint autenticado de portería y ahí
 * se resuelve.
 *
 * Corrección de nivel alto y zona de silencio amplia porque estos carnés van a vivir en
 * el bolsillo de un operario de planta extractora: se doblan, se ensucian de aceite y se
 * mojan. Un QR que solo lee limpio es un QR que no sirve.
 */
class EmployeeQrCodeService
{
    /**
     * UUID v4 (aleatorio), nunca v7: un token ordenado en el tiempo permitiría adivinar
     * el del compañero que entró después y marcarle la entrada.
     */
    public function generateToken(): string
    {
        return (string) Str::uuid();
    }

    public function generateImage(string $token, string $tenantId): string
    {
        $options = new QROptions;
        $options->outputType = QROutputInterface::GDIMAGE_PNG;
        $options->eccLevel = EccLevel::H;
        $options->scale = 10;
        $options->outputBase64 = false;
        $options->addQuietzone = true;
        $options->quietzoneSize = 4;

        $pngBinary = (new QRCode($options))->render($token);

        $path = "employee-qr/{$tenantId}/{$token}.png";

        Storage::disk(persistent_disk())->put($path, $pngBinary);

        return $path;
    }

    public function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk(persistent_disk())->delete($path);
        }
    }

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    public function createForEmployee(Employee $employee): EmployeeQrCode
    {
        $token = $this->generateToken();
        $path = $this->generateImage($token, $employee->tenant_id);

        return $employee->qrCodes()->create([
            'tenant_id' => $employee->tenant_id,
            'qr_token' => $token,
            'qr_image_path' => $path,
            'is_active' => true,
            'generated_at' => now(),
            'scan_count' => 0,
        ]);
    }

    /**
     * Anula el carné anterior y emite uno nuevo.
     *
     * Es la operación de «se me perdió el carné», y tiene que invalidar el viejo de
     * inmediato: mientras el token siga activo, quien lo encuentre puede marcarle la
     * entrada a su dueño. El registro se conserva en borrado suave porque los escaneos
     * históricos apuntan a él.
     */
    public function regenerate(EmployeeQrCode $qrCode): EmployeeQrCode
    {
        $qrCode->loadMissing('employee');

        return DB::transaction(function () use ($qrCode): EmployeeQrCode {
            $oldImagePath = $qrCode->qr_image_path;

            $qrCode->update(['is_active' => false]);
            $qrCode->delete();

            $newQrCode = $this->createForEmployee($qrCode->employee);

            $this->deleteImage($oldImagePath);

            return $newQrCode;
        });
    }
}
