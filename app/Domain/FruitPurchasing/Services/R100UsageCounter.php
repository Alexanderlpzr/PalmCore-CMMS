<?php

namespace App\Domain\FruitPurchasing\Services;

use App\Models\R100DailyUsage;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Cuenta el uso de la calculadora R-100 para saber si el piloto funciona, sin
 * guardar nada de la persona: ni IP, ni cookies, ni lo que escribe. Solo dos
 * contadores por día. Si contar falla, la calculadora sigue funcionando.
 */
class R100UsageCounter
{
    /** Robots y vistas previas (WhatsApp, Facebook, buscadores) no son personas. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|headless/i';

    public function recordVisit(?string $userAgent): void
    {
        if ($userAgent === null || $userAgent === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1) {
            return;
        }

        $this->increment('visits');
    }

    public function recordCalculation(): void
    {
        $this->increment('calculations');
    }

    /**
     * Los últimos días con uso, del más reciente al más viejo, y el total.
     *
     * @return array{days: list<array{date: Carbon, visits: int, calculations: int}>, visits: int, calculations: int}
     */
    public function summary(int $days = 30): array
    {
        $rows = R100DailyUsage::query()
            ->where('date', '>=', today()->subDays($days - 1))
            ->orderByDesc('date')
            ->get();

        return [
            'days' => $rows->map(fn (R100DailyUsage $row): array => [
                'date' => $row->date,
                'visits' => $row->visits,
                'calculations' => $row->calculations,
            ])->all(),
            'visits' => (int) R100DailyUsage::query()->sum('visits'),
            'calculations' => (int) R100DailyUsage::query()->sum('calculations'),
        ];
    }

    private function increment(string $column): void
    {
        try {
            R100DailyUsage::query()
                ->firstOrCreate(['date' => today()->toDateString()])
                ->increment($column);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
