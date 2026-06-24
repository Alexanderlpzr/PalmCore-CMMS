<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Seed the new tenant with a default plant, process areas, and the full
     * role/permission matrix so it is usable immediately after creation.
     */
    protected function afterCreate(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;

        app(ProvisionTenantBaseStructure::class)->handle($tenant);
    }
}
