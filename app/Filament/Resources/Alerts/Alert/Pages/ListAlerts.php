<?php

namespace App\Filament\Resources\Alerts\Alert\Pages;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Filament\Resources\Alerts\AlertResource;
use App\Filament\Widgets\Lists\AlertKpis;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAlerts extends ListRecords
{
    /** La pestaña de los hallazgos de SST y Ambiental; el menú tiene su propia entrada. */
    public const SAFETY_TAB = 'sst-ambiental';

    protected static string $resource = AlertResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; las que filtran lo hacen al tocarlas. */
    protected function getHeaderWidgets(): array
    {
        return [AlertKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Las alertas de mantenimiento —equipos, OT, inventario— por un lado y las de los
     * hallazgos de SST y Ambiental por otro, para no mezclarlas (2026-11-19).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'mantenimiento' => Tab::make('Mantenimiento')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('category', '!=', AlertCategory::Safety->value)),
            self::SAFETY_TAB => Tab::make('SST y Ambiental')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('category', AlertCategory::Safety->value)),
            'todas' => Tab::make('Todas'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'mantenimiento';
    }
}
