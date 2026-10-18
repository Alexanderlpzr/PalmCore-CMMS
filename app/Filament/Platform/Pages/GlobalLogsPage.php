<?php

namespace App\Filament\Platform\Pages;

use App\Domain\Platform\Services\LogReaderService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class GlobalLogsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Errores';

    protected static ?string $title = 'Errores recientes';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.platform.global-logs';

    /**
     * El log se escribe en UTC (la zona de la aplicación); el superadministrador lee las
     * horas en la de Colombia, que es donde opera la plataforma.
     */
    public const DISPLAY_TIMEZONE = 'America/Bogota';

    /** Cuando está activo, también se muestran los avisos, no solo los errores. */
    public bool $includeWarnings = false;

    public function getSubheading(): ?string
    {
        return 'Horas en hora de Colombia.';
    }

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

        $entries = array_map(
            fn (array $entry): array => [
                ...$entry,
                'at' => $entry['at']?->copy()->timezone(self::DISPLAY_TIMEZONE),
            ],
            app(LogReaderService::class)->recent(levels: $levels),
        );

        return [
            'entries' => $entries,
            'includeWarnings' => $this->includeWarnings,
        ];
    }
}
