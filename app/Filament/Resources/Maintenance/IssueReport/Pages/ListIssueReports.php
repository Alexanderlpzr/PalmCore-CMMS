<?php

namespace App\Filament\Resources\Maintenance\IssueReport\Pages;

use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use Filament\Resources\Pages\ListRecords;

class ListIssueReports extends ListRecords
{
    protected static string $resource = IssueReportResource::class;
}
