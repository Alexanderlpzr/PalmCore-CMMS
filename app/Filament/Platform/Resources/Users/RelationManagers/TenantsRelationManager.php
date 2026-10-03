<?php

namespace App\Filament\Platform\Resources\Users\RelationManagers;

use App\Domain\Platform\Services\PlatformUserService;
use App\Filament\Platform\Resources\Users\Schemas\UserForm;
use App\Filament\Platform\Resources\Users\UserActions;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Las empresas a las que entra la persona y con qué rol en cada una.
 *
 * Los roles de Spatie se separan por empresa: tener «administrador general» en una no
 * dice nada de la otra. Por eso el rol se cambia empresa por empresa, desde aquí, y no
 * con un campo único en el formulario de la cuenta.
 */
class TenantsRelationManager extends RelationManager
{
    protected static string $relationship = 'tenants';

    protected static ?string $title = 'Empresas y roles';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedBuildingOffice2;

    public function isReadOnly(): bool
    {
        // Las acciones de esta pestaña son el sitio donde se cambia el rol, también
        // desde la ficha de solo lectura.
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->paginated(false)
            ->emptyStateHeading('No pertenece a ninguna empresa')
            ->emptyStateDescription('Sin empresa no puede entrar a ningún panel. Déle acceso a una.')
            ->columns([
                TextColumn::make('name')
                    ->label('Empresa'),
                TextColumn::make('roles')
                    ->label('Rol')
                    ->badge()
                    ->state(fn (Tenant $record): array => array_map(
                        Role::humanizeName(...),
                        app(PlatformUserService::class)->rolesIn($this->user(), $record),
                    ))
                    ->placeholder('Sin rol'),
                IconColumn::make('is_primary_tenant')
                    ->label('Principal')
                    ->tooltip('La empresa a la que entra al iniciar sesión.')
                    ->boolean()
                    ->state(fn (Tenant $record): bool => (bool) $record->pivot?->is_primary_tenant)
                    ->alignCenter(),
                TextColumn::make('joined_at')
                    ->label('Desde')
                    ->state(fn (Tenant $record): mixed => $record->pivot?->joined_at)
                    ->date('d/m/Y'),
            ])
            ->headerActions([
                $this->addToTenantAction(),
            ])
            ->recordActions([
                $this->changeRoleAction(),
                $this->removeFromTenantAction(),
            ]);
    }

    private function addToTenantAction(): Action
    {
        return Action::make('addToTenant')
            ->label('Dar acceso a una empresa')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(fn (): string => "Dar acceso a {$this->user()->name}")
            ->modalSubmitActionLabel('Dar acceso')
            ->schema([
                Select::make('tenant_id')
                    ->label('Empresa')
                    ->options(fn (): array => Tenant::withoutGlobalScopes()
                        ->whereNotIn('id', $this->user()->tenants()->pluck('tenants.id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('role', null)),
                Select::make('role')
                    ->label('Rol')
                    ->options(fn (Get $get): array => UserForm::rolesOf($get('tenant_id')))
                    ->disabled(fn (Get $get): bool => blank($get('tenant_id')))
                    ->required()
                    ->native(false),
            ])
            ->action(fn (array $data) => UserActions::run(
                fn () => app(PlatformUserService::class)->addToTenant(
                    $this->user(),
                    Tenant::withoutGlobalScopes()->findOrFail($data['tenant_id']),
                    $data['role'],
                    $this->actor(),
                ),
                'Acceso concedido',
            ));
    }

    private function changeRoleAction(): Action
    {
        return Action::make('changeRole')
            ->label('Cambiar rol')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->modalHeading(fn (Tenant $record): string => "Rol de {$this->user()->name} en {$record->name}")
            ->modalSubmitActionLabel('Cambiar')
            ->fillForm(fn (Tenant $record): array => [
                'role' => app(PlatformUserService::class)->rolesIn($this->user(), $record)[0] ?? null,
            ])
            ->schema(fn (Tenant $record): array => [
                Select::make('role')
                    ->label('Rol')
                    ->options(app(PlatformUserService::class)->rolesFor($record))
                    ->required()
                    ->native(false),
            ])
            ->action(fn (Tenant $record, array $data) => UserActions::run(
                fn () => app(PlatformUserService::class)->changeRole($this->user(), $record, $data['role'], $this->actor()),
                'Rol cambiado',
            ));
    }

    private function removeFromTenantAction(): Action
    {
        return Action::make('removeFromTenant')
            ->label('Quitar acceso')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Tenant $record): string => "Quitar a {$this->user()->name} de {$record->name}")
            ->modalDescription('Deja de entrar a esa empresa. Sus OT, paros y registros en ella se conservan con su nombre.')
            ->modalSubmitActionLabel('Quitar acceso')
            ->action(fn (Tenant $record) => UserActions::run(
                fn () => app(PlatformUserService::class)->removeFromTenant($this->user(), $record, $this->actor()),
                'Acceso retirado',
            ));
    }

    private function user(): User
    {
        /** @var User */
        return $this->getOwnerRecord();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
