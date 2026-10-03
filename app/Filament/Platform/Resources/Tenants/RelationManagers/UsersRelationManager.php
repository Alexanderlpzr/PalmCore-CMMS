<?php

namespace App\Filament\Platform\Resources\Tenants\RelationManagers;

use App\Domain\Platform\Services\PlatformUserService;
use App\Filament\Platform\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * La gente de la empresa, con su rol en ella. Antes, para saber quién pertenecía a una
 * empresa había que ir a Usuarios y filtrar. Cada fila lleva a la ficha de la persona,
 * que es donde se cambia todo lo demás.
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Usuarios';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedUsers;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->users()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nadie pertenece todavía a esta empresa')
            ->emptyStateDescription('Sin usuarios, nadie puede entrar a su panel.')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('roles')
                    ->label('Rol')
                    ->badge()
                    ->state(fn (User $record): array => array_map(
                        Role::humanizeName(...),
                        app(PlatformUserService::class)->rolesIn($record, $this->tenant()),
                    ))
                    ->placeholder('Sin rol'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('last_login_at')
                    ->label('Último ingreso')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->placeholder('Nunca'),
            ])
            ->headerActions([
                Action::make('newUser')
                    ->label('Nuevo usuario en esta empresa')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->url(fn (): string => UserResource::getUrl('create', ['tenant' => $this->tenant()->getKey()])),
            ]);
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return $this->getOwnerRecord();
    }
}
