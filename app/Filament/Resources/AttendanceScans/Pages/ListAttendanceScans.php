<?php

namespace App\Filament\Resources\AttendanceScans\Pages;

use App\Filament\Resources\AttendanceScans\AttendanceMarkActions;
use App\Filament\Resources\AttendanceScans\AttendanceScanResource;
use App\Filament\Resources\Concerns\HasBackAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceScans extends ListRecords
{
    use HasBackAction;

    protected static string $resource = AttendanceScanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getHistoryBackAction(),
            AttendanceMarkActions::add(),
        ];
    }
}
