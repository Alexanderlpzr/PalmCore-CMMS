<?php

namespace App\Filament\Platform\Resources\Tenants\RelationManagers;

use App\Filament\Resources\Plants\Schemas\PlantForm;
use App\Filament\Resources\Plants\Tables\PlantsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlantsRelationManager extends RelationManager
{
    protected static string $relationship = 'plants';

    protected static ?string $title = 'Plantas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->plants()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return PlantForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PlantsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
