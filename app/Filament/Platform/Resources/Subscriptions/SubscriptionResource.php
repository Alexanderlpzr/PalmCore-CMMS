<?php

namespace App\Filament\Platform\Resources\Subscriptions;

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\Subscriptions\Pages\EditSubscription;
use App\Filament\Platform\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Suscripciones';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Suscripciones';

    protected static ?string $modelLabel = 'Suscripción';

    protected static ?string $pluralModelLabel = 'Suscripciones';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('subscription_plan')
                ->label('Plan')
                ->required(),
            Select::make('subscription_status')
                ->label('Estado')
                ->options(collect(SubscriptionStatus::cases())->mapWithKeys(
                    fn (SubscriptionStatus $status): array => [$status->value => $status->label()]
                ))
                ->required(),
            DatePicker::make('subscription_expires_at')
                ->label('Fecha de expiración')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->copyable()
                    ->fontFamily('mono')
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
                    }),
                TextColumn::make('subscription_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (SubscriptionStatus $state): string => $state->color())
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label()),
                TextColumn::make('subscription_expires_at')
                    ->label('Expira')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('subscription_status')
                    ->label('Estado')
                    ->options(collect(SubscriptionStatus::cases())->mapWithKeys(
                        fn (SubscriptionStatus $status): array => [$status->value => $status->label()]
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'edit' => EditSubscription::route('/{record}/edit'),
        ];
    }
}
