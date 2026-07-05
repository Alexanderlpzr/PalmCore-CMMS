<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Role;
use App\Models\User;
use App\Services\ImpersonationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Http\RedirectResponse;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenants.name')
                    ->label('Empresas')
                    ->badge()
                    ->separator(',')
                    ->visible(fn (): bool => auth()->user()?->is_super_admin ?? false),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn (string $state): string => Role::humanizeName($state)),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('Último acceso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('impersonate')
                    ->label('Impersonar')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Iniciar impersonación')
                    ->modalDescription(fn (User $record): string => "Ingresarás temporalmente como {$record->name}. Toda la sesión quedará auditada.")
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo (opcional)')
                            ->maxLength(500)
                            ->rows(2),
                    ])
                    // Only an active Super Admin may impersonate, never another
                    // Super Admin, and not while already impersonating.
                    ->visible(fn (User $record): bool => auth()->user()?->is_super_admin
                        && auth()->user()?->is_active
                        && ! $record->is_super_admin
                        && $record->is_active
                        && ! app(ImpersonationService::class)->isImpersonating())
                    ->action(function (User $record, array $data): RedirectResponse {
                        app(ImpersonationService::class)->start(
                            auth()->user(),
                            $record,
                            $data['reason'] ?? null,
                            request(),
                        );

                        return redirect('/admin');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Per-record policy authorization drops protected users (e.g. the
                    // last active Super Admin) from the batch. UserObserver is the
                    // hard backstop for the super-admin actor that Gate::before bypasses.
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                    ForceDeleteBulkAction::make()
                        ->authorizeIndividualRecords('forceDelete'),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
