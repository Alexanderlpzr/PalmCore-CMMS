<?php

namespace App\Filament\Resources\Automation\AutomationRule;

use App\Filament\Resources\Automation\AutomationRule\Pages\EditAutomationRule;
use App\Filament\Resources\Automation\AutomationRule\Pages\ListAutomationRules;
use App\Filament\Resources\Automation\AutomationRule\Schemas\AutomationRuleForm;
use App\Filament\Resources\Automation\AutomationRule\Tables\AutomationRuleTable;
use App\Models\AutomationRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AutomationRuleResource extends Resource
{
    protected static ?string $model = AutomationRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $modelLabel = 'Automatización';

    protected static ?string $pluralModelLabel = 'Automatizaciones';

    protected static string|UnitEnum|null $navigationGroup = 'Automatizaciones';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return AutomationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationRuleTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationRules::route('/'),
            'edit' => EditAutomationRule::route('/{record}/edit'),
        ];
    }
}
