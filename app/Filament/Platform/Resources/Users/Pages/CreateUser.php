<?php

namespace App\Filament\Platform\Resources\Users\Pages;

use App\Domain\Platform\Services\PlatformUserService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Platform\Resources\Users\UserActions;
use App\Filament\Platform\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Dar de alta a una persona en cualquier empresa sin entrar a su panel. Reemplaza al
 * comando `payroll:create-user`, que era la única forma de crear cuentas de RRHH o de
 * portería sin pasar por el panel de la empresa.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Solo vive entre el alta y la notificación que la enseña: no se guarda en claro. */
    private ?string $temporaryPassword = null;

    /**
     * Desde la ficha de una empresa se llega con `?tenant=`: la empresa ya viene
     * elegida y solo falta el nombre, el correo y el rol.
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $tenantId = request()->query('tenant');

        $this->form->fill(
            is_string($tenantId) && Tenant::withoutGlobalScopes()->whereKey($tenantId)->exists()
                ? ['tenant_id' => $tenantId]
                : [],
        );

        $this->callHook('afterFill');
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $created = app(PlatformUserService::class)->create(
                $data['name'],
                $data['email'],
                Tenant::withoutGlobalScopes()->findOrFail($data['tenant_id']),
                $data['role'],
                $this->actor(),
            );
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            $this->halt();
        }

        $this->temporaryPassword = $created['password'];

        return $created['user'];
    }

    /** La sustituye la de la contraseña temporal, que dice lo mismo y más. */
    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        UserActions::showPasswordOnce($user, (string) $this->temporaryPassword);

        $this->temporaryPassword = null;
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('view', ['record' => $this->record]);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
