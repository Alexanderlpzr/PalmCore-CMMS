<?php

namespace App\Domain\Platform\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Los últimos errores, sin entrar por SSH.
 *
 * No pretende reemplazar a Sentry: pretende que el superadministrador pueda ver qué se
 * rompió sin abrir una terminal. Lee solo el final del archivo —un log de producción
 * puede pesar cientos de megas— y devuelve las entradas más recientes.
 */
class LogReaderService
{
    /** Cuántos bytes del final del archivo se leen. Suficiente para las últimas entradas. */
    private const TAIL_BYTES = 256 * 1024;

    /**
     * @param  list<string>  $levels  niveles a incluir (vacío = todos)
     * @return list<array{at: ?Carbon, level: string, message: string, context: string}>
     */
    public function recent(int $limit = 50, array $levels = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']): array
    {
        $path = storage_path('logs/laravel.log');

        if (! is_readable($path)) {
            return [];
        }

        try {
            $contents = $this->tail($path);
        } catch (Throwable) {
            return [];
        }

        // Cada entrada empieza con «[2026-07-13 21:04:11] production.ERROR: …».
        $parts = preg_split(
            '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s+(\w+)\.(\w+):/m',
            $contents,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        $entries = [];

        // El primer trozo es lo que quedó de una entrada cortada por el tail: se descarta.
        for ($i = 1; $i + 3 <= count($parts); $i += 4) {
            $level = strtoupper($parts[$i + 2]);

            if ($levels !== [] && ! in_array($level, $levels, strict: true)) {
                continue;
            }

            $body = trim($parts[$i + 3] ?? '');

            $entries[] = [
                'at' => $this->parseDate($parts[$i]),
                'level' => $level,
                'message' => Str::limit(strtok($body, "\n") ?: '(sin mensaje)', 220),
                'context' => Str::limit($body, 1200),
            ];
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }

    private function parseDate(string $raw): ?Carbon
    {
        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    private function tail(string $path): string
    {
        $size = filesize($path);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return '';
        }

        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
        }

        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }
}
