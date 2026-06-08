<?php

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

class UnauthorizedTenantAccessException extends RuntimeException
{
    public function __construct(string $message = 'Access to this tenant is not authorized.')
    {
        parent::__construct($message);
    }
}
