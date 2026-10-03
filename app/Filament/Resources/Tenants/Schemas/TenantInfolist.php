<?php

namespace App\Filament\Resources\Tenants\Schemas;

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Filament\Resources\Tenants\Tables\TenantsTable;
use App\Models\Tenant;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * La ficha de una empresa: primero lo que decide si puede trabajar —plan, estado,
 * vencimiento—, después sus datos, y al final la región, que casi nunca se mira.
 */
class TenantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan y acceso')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subscription_plan')
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
                        TextEntry::make('subscription_status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (SubscriptionStatus $state): string => $state->color())
                            ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label()),
                        TextEntry::make('subscription_expires_at')
                            ->label('Vence')
                            ->date('d/m/Y')
                            ->color(fn (Tenant $record): ?string => TenantsTable::expiryColor($record))
                            ->placeholder('Sin vencimiento'),
                        // Lo que aplica hoy, no lo que dice el estado guardado: una empresa
                        // «Activa» con el plan vencido ya está en solo consulta.
                        TextEntry::make('access')
                            ->label('Acceso')
                            ->badge()
                            ->state(fn (Tenant $record): string => $record->effectiveSubscriptionStatus()->allowsMutations() ? 'Completo' : 'Solo consulta')
                            ->color(fn (Tenant $record): string => $record->effectiveSubscriptionStatus()->allowsMutations() ? 'success' : 'warning'),
                    ]),

                Section::make('Datos de la empresa')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('tax_id')
                            ->label('NIT')
                            ->placeholder('—'),
                        TextEntry::make('slug')
                            ->label('Dirección del panel')
                            ->formatStateUsing(fn (string $state): string => preg_replace('#^https?://#', '', url("/admin/{$state}")))
                            ->copyable()
                            ->copyableState(fn (string $state): string => url("/admin/{$state}")),
                        TextEntry::make('contact_email')
                            ->label('Correo de contacto')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('contact_phone')
                            ->label('Teléfono')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Cliente desde')
                            ->date('d/m/Y'),
                        TextEntry::make('address')
                            ->label('Dirección')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Región')
                    ->columnSpanFull()
                    ->columns(3)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('country_code')
                            ->label('País')
                            ->placeholder('—'),
                        TextEntry::make('timezone')
                            ->label('Zona horaria')
                            ->placeholder('—'),
                        TextEntry::make('locale')
                            ->label('Idioma y formato')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
