<?php

namespace App\Filament\Widgets\Alerts;

use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Alerts\Services\AlertService;
use App\Filament\Resources\Alerts\AlertResource;
use App\Models\Alert;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CriticalAlertsWidget extends BaseWidget
{
    protected static ?string $heading = 'Alertas críticas abiertas';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Alert::query()
                    ->where('status', AlertStatus::Open->value)
                    ->where('severity', 'critical')
                    ->orderByDesc('created_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                TextColumn::make('title')
                    ->label('Alerta')
                    ->wrap()
                    ->weight('semibold'),

                TextColumn::make('entity_type')
                    ->label('Entidad')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Generada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Alert $record): string => AlertResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Sin alertas críticas')
            ->emptyStateDescription('No hay alertas críticas abiertas en este momento.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }

    public static function getOpenCriticalCount(): int
    {
        return app(AlertService::class)->getOpenCriticalCount(Filament::getTenant()->id);
    }
}
