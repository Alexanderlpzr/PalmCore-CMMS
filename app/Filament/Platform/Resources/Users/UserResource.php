<?php

namespace App\Filament\Platform\Resources\Users;

use App\Domain\Platform\Services\PlatformUserService;
use App\Domain\Platform\Services\UserPasswordService;
use App\Filament\Platform\Resources\Users\Pages\CreateUser;
use App\Filament\Platform\Resources\Users\Pages\EditUser;
use App\Filament\Platform\Resources\Users\Pages\ListUsers;
use App\Filament\Platform\Resources\Users\Pages\ViewUser;
use App\Filament\Platform\Resources\Users\RelationManagers\AccountChangesRelationManager;
use App\Filament\Platform\Resources\Users\RelationManagers\ImpersonationsRelationManager;
use App\Filament\Platform\Resources\Users\RelationManagers\LoginLogsRelationManager;
use App\Filament\Platform\Resources\Users\RelationManagers\TenantsRelationManager;
use App\Filament\Platform\Resources\Users\Schemas\UserForm;
use App\Filament\Platform\Resources\Users\Schemas\UserInfolist;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Todas las cuentas de la plataforma, de todas las empresas, en un solo sitio.
 *
 * Desde aquí el superadministrador da de alta a cualquier persona en cualquier empresa,
 * cambia su nombre, su correo, su rol y sus empresas, le cambia la contraseña, le
 * cierra las sesiones, le quita la verificación en dos pasos si perdió el teléfono,
 * entra como ella, la desactiva o la nombra superadministradora. Antes, la mitad de
 * eso pedía entrar al panel de su empresa y la otra mitad, la consola.
 *
 * No se borra a nadie: se desactiva. Las OT, los paros y la auditoría apuntan a la
 * persona, y una cuenta borrada los dejaría señalando a nadie.
 *
 * Las contraseñas no se muestran porque no se pueden mostrar: se guardan con un hash que
 * no se revierte. Lo que se ofrece es reemplazarlas (ver {@see UserPasswordService}).
 * Las reglas y la auditoría de todo lo demás están en {@see PlatformUserService}.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Empresas';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return self::isSuperAdmin();
    }

    /**
     * Ver y editar también exigen ser superadministrador, aunque el panel ya lo exija
     * con `EnsureSuperAdmin`. La política de usuarios deja ver y editar a quien tenga
     * `users.update` —un administrador de empresa—, y aquí eso sería cualquier cuenta
     * de cualquier empresa. Que no dependa de una sola línea de middleware.
     */
    public static function canView(Model $record): bool
    {
        return self::isSuperAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return self::isSuperAdmin();
    }

    /**
     * Nadie se borra, ni siquiera el superadministrador: se desactiva. Explícito aquí
     * porque `Gate::before` le concede todo y Filament mostraría la acción de borrar.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('tenants:id,name'))
            ->defaultSort('name')
            ->recordUrl(fn (User $record): string => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('tenants.name')
                    ->label('Empresa')
                    ->badge()
                    ->color('gray')
                    ->placeholder(fn (User $record): string => $record->is_super_admin ? 'Plataforma' : 'Sin empresa'),
                IconColumn::make('is_super_admin')
                    ->label('Superadmin')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedShieldCheck)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->falseColor('gray')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
                // Lo que interesa de una contraseña que no se puede ver: si sigue siendo
                // la que alguien más le dio, y desde cuándo.
                TextColumn::make('password_changed_at')
                    ->label('Contraseña')
                    ->badge()
                    ->state(fn (User $record): string => match (true) {
                        $record->must_change_password => 'Temporal',
                        $record->password_changed_at !== null => 'Cambiada '.$record->password_changed_at->diffForHumans(),
                        default => 'Original',
                    })
                    ->color(fn (User $record): string => $record->must_change_password ? 'warning' : 'gray')
                    ->tooltip(fn (User $record): ?string => $record->must_change_password
                        ? 'Debe elegir una propia la próxima vez que entre.'
                        : null),
                TextColumn::make('last_login_at')
                    ->label('Último ingreso')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tenant')
                    ->label('Empresa')
                    ->options(fn (): array => Tenant::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $tenantId): Builder => $q->whereHas(
                            'tenants',
                            fn (Builder $tenants): Builder => $tenants->where('tenants.id', $tenantId),
                        ),
                    )),
                TernaryFilter::make('is_active')->label('Activo'),
                TernaryFilter::make('is_super_admin')->label('Superadministrador'),
                TernaryFilter::make('must_change_password')->label('Con contraseña temporal'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver ficha'),
                    EditAction::make()->label('Editar datos'),
                    ...UserActions::all(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TenantsRelationManager::class,
            LoginLogsRelationManager::class,
            ImpersonationsRelationManager::class,
            AccountChangesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    private static function isSuperAdmin(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }
}
