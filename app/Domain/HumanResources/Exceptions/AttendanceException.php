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

    public static function alreadyVoided(): self
    {
        return new self('Esa marca ya estaba anulada.');
    }

    public static function markInTheFuture(): self
    {
        return new self('La marca no puede quedar con una hora que todavía no ha pasado.');
    }
}
