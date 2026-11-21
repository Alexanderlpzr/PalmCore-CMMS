<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Models\AuditLog;
use App\Models\SalesDispatch;
use BackedEnum;
use Carbon\CarbonInterface;

/**
 * Quién cambió qué en la factura de un cargue, leído de la auditoría. La auditoría guarda
 * lo anterior ya convertido (fechas y estados) y lo nuevo tal como va a la base, así que
 * los dos lados se normalizan antes de compararlos.
 */
class InvoiceHistory
{
    /** @var array<string, string> */
    private const FIELDS = [
        'invoice_number' => 'Número',
        'invoiced_on' => 'Fecha',
        'invoice_amount' => 'Valor',
        'status' => 'Estado',
        'paid_on' => 'Fecha de pago',
    ];

    /**
     * Una línea por cambio, el más reciente primero.
     *
     * @return list<string>
     */
    public function lines(SalesDispatch $dispatch): array
    {
        $timezone = filament()->getTenant()?->plantTimezone() ?? 'America/Bogota';

        return AuditLog::query()
            ->with('user')
            ->where('auditable_type', SalesDispatch::class)
            ->where('auditable_id', $dispatch->getKey())
            ->where('event', 'updated')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AuditLog $log) use ($timezone): ?string {
                $changes = $this->changes($log->old_values ?? [], $log->new_values ?? []);

                if ($changes === []) {
                    return null;
                }

                $when = $log->created_at->setTimezone($timezone)->format('d/m/Y H:i');
                $who = $log->user?->name ?? 'Sistema';

                return "{$when} · {$who}: ".implode('; ', $changes);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return list<string>
     */
    private function changes(array $old, array $new): array
    {
        $changes = [];

        foreach (self::FIELDS as $field => $label) {
            $before = $this->normalize($field, $old[$field] ?? null);
            $after = $this->normalize($field, $new[$field] ?? null);

            if ($before !== $after) {
                $changes[] = "{$label}: {$this->display($field, $before)} → {$this->display($field, $after)}";
            }
        }

        return $changes;
    }

    private function normalize(string $field, mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof CarbonInterface) {
            $value = $value->toDateString();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'invoiced_on', 'paid_on' => substr((string) $value, 0, 10),
            'invoice_amount' => number_format((float) $value, 2, '.', ''),
            default => (string) $value,
        };
    }

    private function display(string $field, ?string $value): string
    {
        if ($value === null) {
            return '—';
        }

        return match ($field) {
            'invoiced_on', 'paid_on' => implode('/', array_reverse(explode('-', $value))),
            'invoice_amount' => '$'.number_format((float) $value, 0, ',', '.'),
            'status' => DispatchStatus::tryFrom($value)?->label() ?? $value,
            default => $value,
        };
    }
}
