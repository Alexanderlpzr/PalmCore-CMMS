<?php

namespace App\Exceptions;

use RuntimeException;

class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $detail = null)
    {
        parent::__construct($message);
    }
}
