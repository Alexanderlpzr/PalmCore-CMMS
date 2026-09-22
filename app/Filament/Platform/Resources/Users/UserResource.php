<?php

namespace App\Filament\Platform\Resources\Users;

use App\Domain\Platform\Services\UserPasswordService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Platform\Resources\Users\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SuperAdminGuard;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * Todas las cuentas de la plataforma, de todas las empresas, en una sola lista.
 *
 * Antes, para ayudar a alguien que olvidó su contraseña, había que entrar al panel de
 * su empresa, ir a Usuarios y editar la ficha. Aquí se busca por correo y se resuelve
 * desde la fila.
 *
 * Las contraseñas no se muestran porque no se pueden mostrar: se guardan con un hash que
 * no se revierte. Lo que se ofrece es reemplazarlas (ver {@see UserPasswordService}).
 * Crear cuentas y asignar roles sigue en el panel de cada empresa, donde está el
 * contexto del tenant que los roles necesitan.
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

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('tenants:id,name'))
            ->defaultSort('name')
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
                TernaryFilter::make('must_change_password')->label('Con contraseña temporal'),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::setPasswordAction(),
                    self::temporaryPasswordAction(),
                    self::toggleActiveAction(),
                ]),
            ]);
    }

    /** El superadministrador escribe la contraseña nueva. */
    private static function setPasswordAction(): Action
    {
        return Action::make('setPassword')
            ->label('Cambiar contraseña')
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn (User $record): bool => self::isSomeoneElse($record))
            ->modalHeading(fn (User $record): string => "Cambiar la contraseña de {$record->name}")
            ->modalDescription('Se cerrarán todas sus sesiones abiertas, en la web y en la app móvil.')
            ->modalSubmitActionLabel('Cambiar contraseña')
            ->schema([
                Toggle::make('must_change')
                    ->label('Que la cambie por una propia al entrar')
                    ->helperText('Recomendado: así solo esa persona conocerá su contraseña definitiva.')
                    ->default(true)
                    ->live(),
                TextInput::make('password')
                    ->label('Contraseña nueva')
                    ->password()
                    ->revealable()
                    ->required()
                    // Una temporal solo dura hasta el primer ingreso; la definitiva tiene
                    // que cumplir la política completa.
                    ->rule(fn (Get $get): Password => $get('must_change') ? Password::min(8) : Password::default())
                    ->same('password_confirmation'),
                TextInput::make('password_confirmation')
                    ->label('Repita la contraseña')
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (User $record, array $data): void {
                app(UserPasswordService::class)->setPassword($record, $data['password'], (bool) $data['must_change']);

                Notification::make()
                    ->title('Contraseña cambiada')
                    ->body($data['must_change']
                        ? "{$record->name} tendrá que elegir una propia al entrar."
                        : "{$record->name} ya puede entrar con la contraseña nueva.")
                    ->success()
                    ->send();
            });
    }

    /** El sistema la genera y la muestra una sola vez. */
    private static function temporaryPasswordAction(): Action
    {
        return Action::make('temporaryPassword')
            ->label('Generar contraseña temporal')
            ->icon(Heroicon::OutlinedSparkles)
            ->visible(fn (User $record): bool => self::isSomeoneElse($record))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Contraseña temporal para {$record->name}")
            ->modalDescription('Se cerrarán sus sesiones abiertas. La contraseña se mostrará una sola vez y tendrá que cambiarla al entrar.')
            ->modalSubmitActionLabel('Generar')
            ->action(function (User $record): void {
                $password = app(UserPasswordService::class)->generateTemporary($record);

                Notification::make()
                    ->title("Contraseña temporal de {$record->name}")
                    ->body("**{$password}**\n\nCópiela ahora: no se vuelve a mostrar. Correo: {$record->email}")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    private static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (User $record): string => $record->is_active ? 'Desactivar' : 'Activar')
            ->icon(fn (User $record): Heroicon => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
            ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
            ->visible(fn (User $record): bool => self::isSomeoneElse($record))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => $record->is_active
                ? 'No podrá entrar hasta que se active de nuevo. Sus datos y su historia se conservan.'
                : 'Podrá volver a entrar con su contraseña actual.')
            ->action(function (User $record): void {
                try {
                    DB::transaction(function () use ($record): void {
                        if ($record->is_active) {
                            app(SuperAdminGuard::class)->assertAnotherActiveSuperAdminExists($record, SuperAdminGuard::MESSAGE_DEACTIVATE);
                        }

                        $record->forceFill(['is_active' => ! $record->is_active])->save();
                    });
                } catch (BusinessRuleException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($record->is_active ? 'Usuario activado' : 'Usuario desactivado')
                    ->success()
                    ->send();
            });
    }

    /** La propia cuenta se gestiona desde el Perfil, no desde aquí. */
    private static function isSomeoneElse(User $record): bool
    {
        return $record->getKey() !== auth()->id();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
