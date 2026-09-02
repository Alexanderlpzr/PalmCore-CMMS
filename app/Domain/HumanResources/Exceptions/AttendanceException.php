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
}
