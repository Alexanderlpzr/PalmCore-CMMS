<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Actions\Tenants\CreateTenantAdmin;
use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Domain\Platform\Services\UserPasswordService;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Filament\Platform\Resources\Users\UserActions;
use App\Filament\Resources\Tenants\Schemas\TenantForm;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * El administrador inicial, apartado de los datos del formulario para que no se
     * intente guardar como columna de la empresa.
     *
     * @var array{name: string, email: string}|null
     */
    private ?array $adminData = null;

    /**
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
            ];
        }

        unset($data['admin_name'], $data['admin_email']);

        return TenantForm::withAccessFromStatus($data);
    }

    /**
     * Siembra la planta, las áreas y los roles de la empresa nueva y, si se pidió,
     * crea a su administrador.
     *
     * El administrador nuevo recibe una contraseña temporal que se muestra una sola vez
     * y que tiene que cambiar al entrar. Antes, si el campo quedaba vacío, nacía con
     * «Admin123» —la misma clave para cada empresa nueva—, y si se escribía una, quedaba
     * como definitiva: quien creaba la empresa conocía para siempre su contraseña.
     */
    protected function afterCreate(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;

        app(ProvisionTenantBaseStructure::class)->handle($tenant);

        if ($this->adminData === null) {
            return;
        }

        $alreadyExists = User::where('email', $this->adminData['email'])->exists();

        $admin = app(CreateTenantAdmin::class)->handle(
            $tenant,
            $this->adminData['name'],
            $this->adminData['email'],
            // Inservible a propósito: la que vale es la temporal de abajo. A una cuenta
            // que ya existía, la acción no le toca la contraseña.
            Str::password(40),
        );

        if ($alreadyExists) {
            Notification::make()
                ->title("{$admin->name} ya tenía cuenta")
                ->body("Ahora también administra {$tenant->name}, con su contraseña de siempre.")
                ->success()
                ->send();

            return;
        }

        UserActions::showPasswordOnce($admin, app(UserPasswordService::class)->generateTemporary($admin));
    }
}
