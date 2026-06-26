<?php

namespace App\Filament\Resources\Audit;

use App\Filament\Resources\Audit\Pages\ListAuditLogs;
use App\Filament\Resources\Audit\Pages\ViewAuditLog;
use App\Filament\Resources\Audit\Schemas\AuditLogInfolist;
use App\Filament\Resources\Audit\Tables\AuditLogTable;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Registro de Auditoría';

    protected static ?string $pluralModelLabel = 'Registros de Auditoría';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 10;

    protected static bool $isScopedToTenant = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuditLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}
