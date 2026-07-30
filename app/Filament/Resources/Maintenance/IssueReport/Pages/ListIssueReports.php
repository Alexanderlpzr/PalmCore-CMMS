<?php

namespace App\Filament\Resources\Maintenance\IssueReport\Pages;

use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use App\Models\EquipmentIssueReport;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListIssueReports extends ListRecords
{
    protected static string $resource = IssueReportResource::class;

    /**
     * Los reportes que nadie ha mirado van aparte de los ya atendidos. Antes
     * todo caía en la misma tabla y no se distinguía qué faltaba por revisar.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pendientes' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open())
                ->badge(fn (): int => EquipmentIssueReport::query()->open()->count())
                ->badgeColor('danger'),

            'atendidos' => Tab::make('Atendidos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->attended())
                ->badge(fn (): int => EquipmentIssueReport::query()->attended()->count()),
        ];
    }
}
