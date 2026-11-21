<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Models\SalesDispatch;
use Illuminate\Validation\ValidationException;

/**
 * Corrige los cuatro datos de la factura de un cargue: número, fecha, valor y estado. Es lo
 * único que puede cambiar la auxiliar de la Directora; pesos, cliente y oferta no pasan por
 * aquí.
 */
class InvoiceEditor
{
    /**
     * @param  array{invoice_number: string, invoiced_on: string, invoice_amount: float|int|string, invoice_status: string, paid_on?: ?string}  $data
     *
     * @throws ValidationException si la marca pagada sin fecha y no venía así del Excel.
     */
    public function update(SalesDispatch $dispatch, array $data): void
    {
        $isMarkedPaid = $data['invoice_status'] === DispatchStatus::Pagado->value;
        $paidOn = $isMarkedPaid ? ($data['paid_on'] ?? null) : null;

        if ($isMarkedPaid && blank($paidOn) && ! $dispatch->isPaidWithoutDate()) {
            throw ValidationException::withMessages(['paid_on' => 'Indique la fecha de pago.']);
        }

        $isReturnedToReceivable = $dispatch->status === DispatchStatus::Pagado && ! $isMarkedPaid;

        $dispatch->fill([
            'invoice_number' => trim($data['invoice_number']),
            'invoiced_on' => $data['invoiced_on'],
            'invoice_amount' => $data['invoice_amount'],
            'paid_on' => blank($paidOn) ? null : $paidOn,
        ]);

        if ($isReturnedToReceivable) {
            $dispatch->status = DispatchStatus::Facturado;
        }

        // Con otro vencimiento, o de nuevo por cobrar, el aviso de vencida vuelve a valer.
        if ($isReturnedToReceivable || $dispatch->isDirty('invoiced_on')) {
            $dispatch->overdue_notified_at = null;
        }

        $dispatch->save();
    }

    /** Cuántos cargues más tienen este número: una factura puede cubrir varios. */
    public function otherLoadsWithNumber(SalesDispatch $dispatch, ?string $number): int
    {
        $number = trim((string) $number);

        if ($number === '') {
            return 0;
        }

        return SalesDispatch::query()
            ->where('invoice_number', $number)
            ->whereKeyNot($dispatch->getKey())
            ->count();
    }
}
