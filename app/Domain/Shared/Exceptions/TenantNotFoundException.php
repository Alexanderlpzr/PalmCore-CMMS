<?php

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

class TenantNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Tenant not found.')
    {
        parent::__construct($message);
    }
}
