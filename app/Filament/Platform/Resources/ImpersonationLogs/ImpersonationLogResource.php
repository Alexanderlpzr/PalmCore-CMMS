<?php

namespace App\Filament\Platform\Resources\ImpersonationLogs;

use App\Filament\Platform\Resources\ImpersonationLogs\Pages\ListImpersonationLogs;
use App\Models\ImpersonationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ImpersonationLogResource extends Resource
{
    protected static ?string $model = ImpersonationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Impersonaciones';

    protected static ?string $modelLabel = 'Registro de impersonación';

    protected static ?string $pluralModelLabel = 'Registros de impersonación';

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
                TextColumn::make('impersonator.name')
                    ->label('Administrador')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('impersonatedUser.name')
                    ->label('Usuario impersonado')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->label('Empresa')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('started_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('duration_seconds')
                    ->label('Duración')
                    ->formatStateUsing(function (?int $state): string {
                        if ($state === null) {
                            return '—';
                        }
                        $minutes = intdiv($state, 60);
                        $seconds = $state % 60;

                        return "{$minutes} min {$seconds} s";
                    }),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('started_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImpersonationLogs::route('/'),
        ];
    }
}
