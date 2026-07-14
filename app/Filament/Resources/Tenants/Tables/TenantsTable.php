<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Domain\Platform\Services\TenantAccessService;
use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Services\ImpersonationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Http\RedirectResponse;

class TenantsTable
{
    /** @var array<string, string> */
    private static array $planLabels = [
        'trial' => 'Prueba',
        'starter' => 'Inicial',
        'professional' => 'Profesional',
        'enterprise' => 'Empresarial',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('tax_id')
                    ->label('RUC / NIT')
                    ->searchable(),
                TextColumn::make('contact_email')
                    ->label('Email')
                    ->searchable(),
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
                    ->formatStateUsing(fn (string $state): string => self::$planLabels[$state] ?? $state),
                TextColumn::make('subscription_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (SubscriptionStatus $state): string => $state->color())
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label()),
                TextColumn::make('subscription_expires_at')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('subscription_plan')
                    ->label('Plan')
                    ->options(self::$planLabels),
                SelectFilter::make('subscription_status')
                    ->label('Estado')
                    ->options(SubscriptionStatus::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::suspendAction(),
                self::reactivateAction(),
                self::impersonateOwnerAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    /**
     * Cortar el acceso. No borra nada: la empresa deja de entrar y su histórico sigue
     * intacto, esperándola.
     */
    private static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label('Suspender')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Suspender la empresa')
            ->modalDescription('Nadie de esta empresa podrá entrar. Sus equipos, órdenes e histórico no se tocan.')
            ->visible(fn (Tenant $record): bool => (auth()->user()?->is_super_admin ?? false)
                && $record->subscription_status !== SubscriptionStatus::Suspended)
            ->action(fn (Tenant $record) => self::run(
                fn () => app(TenantAccessService::class)->suspend($record),
                'Empresa suspendida',
            ));
    }

    private static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label('Reactivar')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->requiresConfirmation()
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
    private static function impersonateOwnerAction(): Action
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
            ->form([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->helperText('Queda registrado en la bitácora de suplantaciones.')
                    ->required()
                    ->minLength(5)
                    ->rows(2),
            ])
            ->visible(fn (Tenant $record): bool => (auth()->user()?->is_super_admin ?? false)
                && ! app(ImpersonationService::class)->isImpersonating()
                && app(TenantAccessService::class)->owner($record) !== null)
            ->action(function (Tenant $record, array $data): RedirectResponse {
                $owner = app(TenantAccessService::class)->owner($record);

                app(ImpersonationService::class)->start(
                    auth()->user(),
                    $owner,
                    $data['reason'],
                    request(),
                );

                return redirect()->to('/admin');
            });
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
