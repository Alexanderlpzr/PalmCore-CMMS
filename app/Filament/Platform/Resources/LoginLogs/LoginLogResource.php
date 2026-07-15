<?php

namespace App\Filament\Platform\Resources\LoginLogs;

use App\Domain\Platform\Enums\LoginLogEvent;
use App\Filament\Platform\Resources\LoginLogs\Pages\ListLoginLogs;
use App\Models\LoginLog;
use App\Models\Tenant;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class LoginLogResource extends Resource
{
    protected static ?string $model = LoginLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Accesos';

    protected static ?string $modelLabel = 'Registro de acceso';

    protected static ?string $pluralModelLabel = 'Registros de acceso';

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
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn (LoginLogEvent $state): string => $state->label())
                    ->color(fn (LoginLogEvent $state): string => $state->color()),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),

                TextColumn::make('user.tenants.name')
                    ->label('Empresas')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user_agent')
                    ->label('Navegador / dispositivo')
                    ->placeholder('—')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Evento')
                    ->options(LoginLogEvent::options()),

                SelectFilter::make('tenant')
                    ->label('Empresa')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['value'] ?? null,
                            fn (Builder $q, string $tenantId): Builder => $q->whereHas(
                                'user.tenants',
                                fn (Builder $tenantQuery) => $tenantQuery->where('tenants.id', $tenantId)
                            )
                        )),

                Filter::make('date_from')
                    ->label('Desde')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Desde')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['date_from'],
                            fn (Builder $q, string $date): Builder => $q->where('occurred_at', '>=', Carbon::parse($date)->startOfDay())
                        )),

                Filter::make('date_to')
                    ->label('Hasta')
                    ->form([
                        DatePicker::make('date_to')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['date_to'],
                            fn (Builder $q, string $date): Builder => $q->where('occurred_at', '<=', Carbon::parse($date)->endOfDay())
                        )),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->striped()
            ->paginated([25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoginLogs::route('/'),
        ];
    }
}
