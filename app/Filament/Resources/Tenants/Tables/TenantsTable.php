<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Domain\Shared\Enums\SubscriptionStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

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
                    ->options(SubscriptionStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
}
