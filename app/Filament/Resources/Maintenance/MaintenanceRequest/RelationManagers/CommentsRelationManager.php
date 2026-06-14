<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comentarios';

    protected static ?string $recordTitleAttribute = 'body';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->comments()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Textarea::make('body')
                            ->label('Comentario')
                            ->required()
                            ->rows(3),
                        Toggle::make('is_internal')
                            ->label('Nota interna')
                            ->helperText('Solo visible para el equipo de mantenimiento')
                            ->default(false),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->sortable(),
                TextColumn::make('body')
                    ->label('Comentario')
                    ->wrap()
                    ->limit(120),
                IconColumn::make('is_internal')
                    ->label('Interno')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['tenant_id'] = $this->ownerRecord->tenant_id;

                        return $data;
                    }),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
