<?php

namespace App\Filament\Platform\Resources\Users\RelationManagers;

use App\Domain\Platform\Enums\LoginLogEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Sus ingresos, salidas e intentos fallidos. Lo primero que se mira cuando alguien
 * dice «no me deja entrar» o cuando se sospecha que otro entró con su cuenta.
 */
class LoginLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'loginLogs';

    protected static ?string $title = 'Ingresos';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedArrowRightEndOnRectangle;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->emptyStateHeading('Todavía no ha entrado nunca')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Cuándo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Qué')
                    ->badge()
                    ->formatStateUsing(fn (LoginLogEvent $state): string => $state->label())
                    ->color(fn (LoginLogEvent $state): string => $state->color()),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—'),
                TextColumn::make('user_agent')
                    ->label('Navegador')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—'),
            ]);
    }
}
