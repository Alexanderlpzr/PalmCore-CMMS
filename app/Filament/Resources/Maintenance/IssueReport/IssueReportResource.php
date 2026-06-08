<?php

namespace App\Filament\Resources\Maintenance\IssueReport;

use App\Filament\Resources\Maintenance\IssueReport\Pages\ListIssueReports;
use App\Filament\Resources\Maintenance\IssueReport\Pages\ViewIssueReport;
use App\Filament\Resources\Maintenance\IssueReport\Schemas\IssueReportInfolist;
use App\Filament\Resources\Maintenance\IssueReport\Tables\IssueReportTable;
use App\Models\EquipmentIssueReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class IssueReportResource extends Resource
{
    protected static ?string $model = EquipmentIssueReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $modelLabel = 'Reporte de Novedad';

    protected static ?string $pluralModelLabel = 'Reportes de Novedad';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    public static function infolist(Schema $schema): Schema
    {
        return IssueReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IssueReportTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIssueReports::route('/'),
            'view'  => ViewIssueReport::route('/{record}'),
        ];
    }
}
