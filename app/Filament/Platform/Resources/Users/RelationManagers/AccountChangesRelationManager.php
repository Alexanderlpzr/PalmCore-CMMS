<?php

namespace App\Filament\Platform\Resources\Users\RelationManagers;

use App\Domain\Platform\Enums\AccountChange;
use App\Models\AuditLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lo que se le cambió a la cuenta desde la plataforma, y quién lo hizo. Responde a la
 * pregunta que llega siempre tarde: «¿quién me cambió el rol?».
 */
class AccountChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'accountChanges';

    protected static ?string $title = 'Cambios';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClock;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user:id,name'))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin cambios registrados')
            ->emptyStateDescription('Se registran los que se hacen desde la plataforma.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Cuándo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Quién')
                    ->placeholder('Sistema'),
                TextColumn::make('event')
                    ->label('Qué')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AccountChange::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => AccountChange::tryFrom($state)?->color() ?? 'gray'),
                TextColumn::make('detail')
                    ->label('Detalle')
                    ->state(fn (AuditLog $record): ?string => AccountChange::tryFrom($record->event)
                        ?->describe($record->old_values, $record->new_values))
                    ->wrap()
                    ->placeholder('—'),
            ]);
    }
}
