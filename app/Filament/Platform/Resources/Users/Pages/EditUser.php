<?php

namespace App\Filament\Platform\Resources\Users\Pages;

use App\Domain\Platform\Services\PlatformUserService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Platform\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Nombre, correo y foto. La empresa y el rol se cambian en la pestaña «Empresas y
 * roles» de la ficha, y el acceso con las acciones: cada cosa con su regla.
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  User  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(PlatformUserService::class)->updateAccount(
                $record,
                $data['name'],
                $data['email'],
                // Si el campo no viene, la foto se queda como está.
                array_key_exists('avatar_path', $data) ? $data['avatar_path'] : false,
                $this->actor(),
            );
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            $this->halt();
        }
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
