<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Domain\Platform\Services\TenantAccessService;
use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Filament\Resources\Tenants\Schemas\TenantForm;
use App\Models\Tenant;
use App\Services\ImpersonationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Las empresas cliente. El plan, el estado y el vencimiento viven aquí: la antigua
 * sección de Suscripciones era esta misma tabla mostrada otra vez, con el plan sin
 * traducir y editable como texto libre.
 */
class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('users'))
            ->recordUrl(fn (Tenant $record): string => TenantResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Empresa')
                    ->description(fn (Tenant $record): ?string => $record->contact_email)
                    ->searchable(['name', 'slug', 'tax_id', 'contact_email'])
                    ->sortable(),
                TextColumn::make('subscription_plan')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'trial' => 'warning',
                        'starter' => 'info',
                        'professional' => 'success',
                        'enterprise' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => TenantForm::PLANS[$state] ?? $state),
                // Una sola columna de estado. Antes había dos —«Estado» y «Activo»— que
                // podían contradecirse; ahora el acceso se deriva del estado al guardar.
                TextColumn::make('subscription_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (SubscriptionStatus $state): string => $state->color())
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label()),
                TextColumn::make('subscription_expires_at')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->color(fn (Tenant $record): ?string => self::expiryColor($record))
                    ->placeholder('Sin vencimiento')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Identificador')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_id')
                    ->label('NIT')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('subscription_plan')
                    ->label('Plan')
                    ->options(TenantForm::PLANS),
                SelectFilter::make('subscription_status')
                    ->label('Estado')
                    ->options(SubscriptionStatus::options()),
                // Los textos de Filament hablan de «registros eliminados»; aquí nada se
                // elimina, se archiva.
                TrashedFilter::make()
                    ->label('Archivadas')
                    ->placeholder('Sin las archivadas')
                    ->trueLabel('Con las archivadas')
                    ->falseLabel('Solo las archivadas'),
            ])
            // En un menú y no en línea: cinco acciones a la vista se salían de la
            // pantalla («Entrar c…»), y «Suspender» quedaba en rojo junto a «Editar».
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver ficha'),
                    EditAction::make()->label('Editar datos'),
                    self::impersonateOwnerAction(),
                    self::suspendAction(),
                    self::reactivateAction(),
                    RestoreAction::make()
                        ->modalHeading(fn (Tenant $record): string => "Restaurar {$record->name}")
                        ->successNotificationTitle('Empresa restaurada'),
                ]),
            ])
            // Archivar se puede deshacer. El borrado definitivo de empresas enteras ya no
            // está en el menú masivo: con una casilla marcada de más se perdía todo.
            ->toolbarActions([
                BulkActionGroup::make([
                    // Sin estos textos la confirmación diría «Borrar» con un botón «Borrar».
                    DeleteBulkAction::make()
                        ->label('Archivar seleccionadas')
                        ->modalHeading('Archivar las empresas seleccionadas')
                        ->modalDescription('Dejan de aparecer en las listas y nadie de ellas puede entrar. Sus datos se conservan y se pueden restaurar.')
                        ->modalSubmitActionLabel('Archivar')
                        ->successNotificationTitle('Empresas archivadas'),
                    RestoreBulkAction::make()
                        ->label('Restaurar seleccionadas')
                        ->modalHeading('Restaurar las empresas seleccionadas')
                        ->successNotificationTitle('Empresas restauradas'),
                ]),
            ])
            ->defaultSort('name');
    }

    /**
     * Dejar a la empresa en solo consulta. No borra nada: su gente sigue entrando a
     * mirar, pero no crea ni cambia nada hasta que se reactive.
     */
    public static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label('Suspender')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Tenant $record): string => "Suspender {$record->name}")
            ->modalDescription('Su gente podrá entrar a consultar, pero no crear ni cambiar nada, y verá el aviso «Cuenta suspendida». Sus equipos, órdenes e histórico no se tocan, y se puede reactivar cuando quiera.')
            ->modalSubmitActionLabel('Suspender')
            ->visible(fn (Tenant $record): bool => (auth()->user()?->is_super_admin ?? false)
                && $record->subscription_status !== SubscriptionStatus::Suspended)
            ->action(fn (Tenant $record) => self::run(
                fn () => app(TenantAccessService::class)->suspend($record),
                'Empresa suspendida',
            ));
    }

    public static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label('Reactivar')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (Tenant $record): string => "Reactivar {$record->name}")
            ->modalDescription('Su gente vuelve a trabajar con normalidad, con sus contraseñas de siempre.')
            ->modalSubmitActionLabel('Reactivar')
            ->visible(fn (Tenant $record): bool => (auth()->user()?->is_super_admin ?? false)
                && $record->subscription_status === SubscriptionStatus::Suspended)
            ->action(fn (Tenant $record) => self::run(
                fn () => app(TenantAccessService::class)->reactivate($record),
                'Empresa reactivada',
            ));
    }

    /**
     * «No me deja hacer X»: la única forma honesta de responder eso es ver la pantalla
     * que el cliente está viendo. La sesión queda auditada de principio a fin.
     */
    public static function impersonateOwnerAction(): Action
    {
        return Action::make('impersonateOwner')
            ->label('Entrar como el dueño')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Entrar como el dueño de la empresa')
            ->modalDescription(fn (Tenant $record): string => ($owner = app(TenantAccessService::class)->owner($record)) !== null
                ? "Ingresarás como {$owner->name}. Toda la sesión queda auditada."
                : 'Esta empresa no tiene un dueño activo a quien suplantar.')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->helperText('Queda registrado en Suplantaciones.')
                    ->required()
                    ->minLength(5)
                    ->rows(2),
            ])
            ->visible(fn (Tenant $record): bool => (auth()->user()?->is_super_admin ?? false)
                && ! $record->trashed()
                && ! app(ImpersonationService::class)->isImpersonating()
                && app(TenantAccessService::class)->owner($record) !== null)
            ->action(function (Tenant $record, array $data, Action $action): void {
                app(ImpersonationService::class)->start(
                    auth()->user(),
                    app(TenantAccessService::class)->owner($record),
                    $data['reason'],
                    request(),
                );

                // Carga completa: cambia la identidad de la sesión y el panel de destino.
                $action->redirect('/admin', navigate: false);
            });
    }

    /**
     * Rojo si ya venció —la empresa está en solo consulta aunque diga «Activo»—, ámbar
     * si vence en los próximos 30 días.
     */
    public static function expiryColor(Tenant $tenant): ?string
    {
        return match (true) {
            $tenant->subscription_expires_at?->isPast() ?? false => 'danger',
            $tenant->isExpiringSoon() => 'warning',
            default => null,
        };
    }

    /** Las reglas viven en el servicio y ya hablan español: aquí solo se muestran. */
    private static function run(callable $operation, string $successMessage): void
    {
        try {
            $operation();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($successMessage)->success()->send();
    }
}
