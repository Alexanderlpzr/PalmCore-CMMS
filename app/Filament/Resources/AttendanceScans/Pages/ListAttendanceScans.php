<?php

namespace App\Filament\Resources\AttendanceScans\Pages;

use App\Filament\Resources\AttendanceScans\AttendanceMarkActions;
use App\Filament\Resources\AttendanceScans\AttendanceScanResource;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Widgets\Lists\AttendanceScanKpis;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceScans extends ListRecords
{
    use HasBackAction;

    protected static string $resource = AttendanceScanResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; cada una filtra la lista al tocarla. */
    protected function getHeaderWidgets(): array
    {
        return [AttendanceScanKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getHistoryBackAction(),
            AttendanceMarkActions::add(),
        ];
    }
}
