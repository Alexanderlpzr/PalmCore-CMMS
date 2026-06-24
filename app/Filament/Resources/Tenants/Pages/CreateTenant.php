<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Actions\Tenants\CreateTenantAdmin;
use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Captured initial-admin fields, pulled out of the form data before the
     * Tenant is created so they are not mass-assigned to the model.
     *
     * @var array{name: string, email: string, password: string}|null
     */
    private ?array $adminData = null;

    /**
     * Strip the non-model admin_* fields from the payload and stash the admin
     * details if an email was provided.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $email = trim((string) ($data['admin_email'] ?? ''));

        if ($email !== '') {
            $this->adminData = [
                'name' => trim((string) ($data['admin_name'] ?? '')) ?: 'Administrador',
                'email' => $email,
                'password' => (string) ($data['admin_password'] ?? '') ?: 'Admin123',
            ];
        }

        unset($data['admin_name'], $data['admin_email'], $data['admin_password']);

        return $data;
    }

    /**
     * Seed the new tenant with a default plant, process areas, and the full
     * role/permission matrix, then optionally create its initial administrator.
     */
    protected function afterCreate(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;

        app(ProvisionTenantBaseStructure::class)->handle($tenant);

        if ($this->adminData !== null) {
            app(CreateTenantAdmin::class)->handle(
                $tenant,
                $this->adminData['name'],
                $this->adminData['email'],
                $this->adminData['password'],
            );
        }
    }
}
