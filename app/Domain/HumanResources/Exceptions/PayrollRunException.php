<?php

namespace App\Domain\HumanResources\Exceptions;

use App\Models\PayrollRun;
use RuntimeException;

class PayrollRunException extends RuntimeException
{
    public static function closed(PayrollRun $run): self
    {
        return new self(sprintf(
            'La nómina «%s» está cerrada. Reábrala si de verdad necesita volver a liquidarla: '
            .'las cifras ya se pagaron y se aportaron.',
            $run->name,
        ));
    }

    public static function notCalculated(PayrollRun $run): self
    {
        return new self(sprintf('La nómina «%s» todavía no se ha liquidado.', $run->name));
    }

    public static function hasWarnings(PayrollRun $run, int $count): self
    {
        return new self(sprintf(
            '%d renglones de «%s» tienen avisos por revisar. Corríjalos o cierre forzando, '
            .'pero cerrar con avisos es firmar una nómina que el propio sistema marcó como dudosa.',
            $count,
            $run->name,
        ));
    }
}
