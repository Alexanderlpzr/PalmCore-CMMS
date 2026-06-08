<?php

use App\Infrastructure\Tenancy\CurrentTenant;

if (! function_exists('current_tenant_id')) {
    function current_tenant_id(): ?string
    {
        return CurrentTenant::id();
    }
}

if (! function_exists('current_tenant')) {
    function current_tenant(): mixed
    {
        return CurrentTenant::get();
    }
}
