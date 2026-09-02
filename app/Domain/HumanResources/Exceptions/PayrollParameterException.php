<?php

namespace App\Domain\HumanResources\Exceptions;

use App\Domain\HumanResources\Enums\PayrollParameter;
use Carbon\CarbonInterface;
use RuntimeException;

class PayrollParameterException extends RuntimeException
{
    public static function missing(PayrollParameter $parameter, CarbonInterface $date): self
    {
        return new self(sprintf(
            'No hay un valor vigente de «%s» para el %s. Cargue la vigencia antes de liquidar ese período.',
            $parameter->label(),
            $date->format('d/m/Y'),
        ));
    }

    public static function wouldRewriteHistory(PayrollParameter $parameter, CarbonInterface $from): self
    {
        return new self(sprintf(
            'Ya existe una vigencia de «%s» posterior al %s. Cambiar el pasado alteraría nóminas ya liquidadas: '
            .'corrija esa vigencia directamente si de verdad es lo que quiere.',
            $parameter->label(),
            $from->format('d/m/Y'),
        ));
    }

    public static function outOfRange(PayrollParameter $parameter, float $value): self
    {
        return new self(sprintf(
            'El valor %s no es razonable para «%s» (%s). Revise si escribió un porcentaje donde va un factor.',
            $value,
            $parameter->label(),
            $parameter->unit()->label(),
        ));
    }
}
