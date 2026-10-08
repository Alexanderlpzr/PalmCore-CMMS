<?php

namespace App\Domain\HumanResources\Exceptions;

use RuntimeException;

class AttendanceException extends RuntimeException
{
    public static function unknownToken(): self
    {
        return new self('Ese carné no corresponde a ningún trabajador activo.');
    }

    public static function inactiveEmployee(string $name, string $status): self
    {
        return new self(sprintf('%s figura como %s y no puede marcar.', $name, mb_strtolower($status)));
    }

    public static function dayAlreadyConfirmed(string $date): self
    {
        return new self(sprintf('El %s ya está confirmado: reábrelo en «Horas por confirmar» antes de corregir sus marcas.', $date));
    }

    public static function dayInClosedPayroll(string $date, string $run): self
    {
        return new self(sprintf('El %s ya se pagó en la nómina «%s», que está cerrada: no se puede cambiar.', $date, $run));
    }

    public static function alreadyVoided(): self
    {
        return new self('Esa marca ya estaba anulada.');
    }

    public static function nothingChanged(): self
    {
        return new self('No cambió ninguna hora: no hay nada que corregir.');
    }

    public static function hoursOutOfRange(): self
    {
        return new self('Las horas de un día van de 0 a 24.');
    }

    public static function dayWithoutMarks(): self
    {
        return new self('Este día no tiene marcas de la puerta. Use «Agregar marca» para registrar la entrada o la salida.');
    }

    public static function markInTheFuture(): self
    {
        return new self('La marca no puede quedar con una hora que todavía no ha pasado.');
    }
}
