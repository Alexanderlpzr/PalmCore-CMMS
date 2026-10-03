<?php

namespace App\Filament\Platform\Resources\Users\RelationManagers;

use App\Models\ImpersonationLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Las veces que un superadministrador entró como esta persona: quién, cuándo, cuánto
 * tiempo y para qué. La persona tiene derecho a que eso conste.
 */
class ImpersonationsRelationManager extends RelationManager
{
    protected static string $relationship = 'impersonationsReceived';

    protected static ?string $title = 'Suplantaciones';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedUserCircle;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['impersonator:id,name', 'tenant:id,name']))
            ->defaultSort('started_at', 'desc')
            ->emptyStateHeading('Nadie ha entrado como esta persona')
            ->columns([
                TextColumn::make('started_at')
                    ->label('Cuándo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('impersonator.name')
                    ->label('Quién'),
                TextColumn::make('tenant.name')
                    ->label('Empresa')
                    ->placeholder('—'),
                TextColumn::make('duration_seconds')
                    ->label('Duración')
                    ->state(fn (ImpersonationLog $record): string => match (true) {
                        $record->ended_at === null => 'En curso',
                        ($record->duration_seconds ?? 0) < 60 => 'Menos de un minuto',
                        default => intdiv($record->duration_seconds, 60).' min',
                    }),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('Sin motivo'),
            ]);
    }
}
