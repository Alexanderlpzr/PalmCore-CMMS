<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\RelationManagers\BonusesRelationManager;
use App\Filament\Resources\Employees\RelationManagers\DeductionsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\NoveltiesRelationManager;
use App\Filament\Resources\Employees\RelationManagers\OvertimeRelationManager;
use App\Filament\Resources\Employees\RelationManagers\QrCodesRelationManager;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Filament\Resources\Employees\Widgets\EmployeeMonthCalendar;
use App\Filament\Resources\Employees\Widgets\EmployeeProfileCard;
use App\Models\Employee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $modelLabel = 'Trabajador';

    protected static ?string $pluralModelLabel = 'Personal';

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano · Personal y ajustes';

    protected static ?string $navigationLabel = 'Personal';

    /** «Horas por confirmar», no «Horas Por Confirmar». */
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    /**
     * Novedades, bonificaciones y descuentos viven aquí y no en pantallas aparte porque
     * se capturan mirando a la persona: llega la incapacidad de fulano y se registra en
     * la ficha de fulano.
     *
     * Los documentos van primero porque en la ficha son la pestaña que sigue a
     * «Información», como la carpeta física que talento humano abre al lado.
     */
    public static function getRelations(): array
    {
        return [
            // El carné va primero: es lo que se busca cuando alguien no puede marcar.
            QrCodesRelationManager::class,
            // El formato de horas extras: lo que marcó en la puerta, como lo firma su jefe.
            OvertimeRelationManager::class,
            DocumentsRelationManager::class,
            NoveltiesRelationManager::class,
            BonusesRelationManager::class,
            DeductionsRelationManager::class,
        ];
    }

    /** @return array<class-string> */
    public static function getWidgets(): array
    {
        return [EmployeeProfileCard::class, EmployeeMonthCalendar::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
