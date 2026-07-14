<?php

namespace App\Filament\Platform\Pages;

use App\Domain\Platform\Services\LogReaderService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class GlobalLogsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Errores recientes';

    protected static ?string $title = 'Errores recientes';

    protected static string|\UnitEnum|null $navigationGroup = 'Observabilidad';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.platform.global-logs';

    /** Cuando está activo, también se muestran los avisos, no solo los errores. */
    public bool $includeWarnings = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleWarnings')
                ->label(fn (): string => $this->includeWarnings ? 'Solo errores' : 'Incluir avisos')
                ->icon(Heroicon::OutlinedFunnel)
                ->color('gray')
                ->action(fn () => $this->includeWarnings = ! $this->includeWarnings),
            Action::make('refresh')
                ->label('Actualizar')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => null),
        ];
    }

    public function getViewData(): array
    {
        $levels = $this->includeWarnings
            ? ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY', 'WARNING']
            : ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

        return [
            'entries' => app(LogReaderService::class)->recent(levels: $levels),
            'includeWarnings' => $this->includeWarnings,
        ];
    }
}
