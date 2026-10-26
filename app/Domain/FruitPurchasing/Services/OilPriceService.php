<?php

namespace App\Domain\FruitPurchasing\Services;

use App\Domain\Platform\Services\PlatformSettingsService;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * El precio de la tonelada de aceite (CPO) con el que la calculadora R-100
 * valora la fruta. Lo publica un super admin en /r100/admin; el proveedor solo
 * lo ve. Vive en los ajustes de la plataforma, que guardan quién lo cambió y cuándo.
 */
class OilPriceService
{
    public const SETTING_KEY = 'r100.oil_price_per_ton';

    /** Menos de esto casi seguro es el precio del KILO escrito por error. */
    public const MINIMUM_PRICE = 500_000;

    /** Más de esto casi seguro es un cero de más. */
    public const MAXIMUM_PRICE = 50_000_000;

    public function __construct(private PlatformSettingsService $settings) {}

    /**
     * El precio vigente. Si nadie lo ha publicado, el de la configuración
     * (R100_OIL_PRICE_PER_TON); si tampoco hay, null.
     *
     * @return array{price: float, updatedAt: Carbon|null, updatedBy: string|null}|null
     */
    public function current(): ?array
    {
        $setting = PlatformSetting::with('updatedBy')->find(self::SETTING_KEY);
        $price = $setting?->value['price'] ?? null;

        if ($price !== null) {
            return [
                'price' => (float) $price,
                'updatedAt' => Carbon::parse($setting->updated_at),
                'updatedBy' => $setting->updatedBy?->name,
            ];
        }

        $configured = config('palmcore.r100.oil_price_per_ton');

        return $configured !== null
            ? ['price' => (float) $configured, 'updatedAt' => null, 'updatedBy' => null]
            : null;
    }

    public function publish(float $pricePerTon, User $actor): void
    {
        if ($pricePerTon < self::MINIMUM_PRICE || $pricePerTon > self::MAXIMUM_PRICE) {
            throw new InvalidArgumentException('El precio de la tonelada está fuera del rango razonable.');
        }

        $this->settings->set(self::SETTING_KEY, ['price' => $pricePerTon], $actor);

        // Volver a publicar el mismo precio también es noticia: confirma que sigue
        // vigente. Sin esto la fecha de «actualizado» no se movería.
        PlatformSetting::find(self::SETTING_KEY)?->touch();
    }
}
