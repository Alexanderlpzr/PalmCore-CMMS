<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Users\UserResource;
use App\Services\SuperAdminGuard;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use HasBackAction;

    protected static string $resource = UserResource::class;

    protected ?array $pendingRoles = null;

    protected string|false|null $pendingAvatarPath = false;

    protected function getHeaderActions(): array
    {
        // The Gate::before super-admin bypass makes policy-based hiding ineffective
        // for super admins, so hide the destructive actions explicitly via the guard.
        $isLastActiveSuperAdmin = fn (): bool => app(SuperAdminGuard::class)
            ->isLastActiveSuperAdmin($this->getRecord());

        return [
            DeleteAction::make()->hidden($isLastActiveSuperAdmin),
            ForceDeleteAction::make()->hidden($isLastActiveSuperAdmin),
            RestoreAction::make(),
            $this->getBackAction(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingRoles = $data['roles'] ?? null;
        unset($data['roles']);

        if (array_key_exists('avatar_path', $data)) {
            $this->pendingAvatarPath = $data['avatar_path'];
            unset($data['avatar_path']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingRoles !== null) {
            $tenantId = Filament::getTenant()?->id;
            setPermissionsTeamId($tenantId);
            $this->record->syncRoles($this->pendingRoles);
        }

        if ($this->pendingAvatarPath !== false) {
            $this->record->profile()->updateOrCreate(
                ['user_id' => $this->record->id],
                ['avatar_path' => $this->pendingAvatarPath],
            );
        }
    }
}
