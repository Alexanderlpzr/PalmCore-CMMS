<?php

namespace App\Domain\Shared\Contracts;

interface TenantAware
{
    public function getTenantId(): string;
}
