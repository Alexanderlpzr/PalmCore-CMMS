<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Models\EquipmentPhoto;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Fotografías';

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->photos()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fotografía')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Imagen')
                            ->required()
                            ->image()
                            ->disk('public')
                            ->directory(fn ($livewire) => 'equipment-photos/'.$livewire->ownerRecord->tenant_id.'/'.$livewire->ownerRecord->id)
                            ->storeFileNamesIn('file_name')
                            ->visibility('public')
                            ->preventFilePathTampering()
                            ->maxSize(10240) // 10 MB
                            ->imageResizeMode('contain')
                            ->columnSpanFull(),
                        TextInput::make('caption')
                            ->label('Leyenda')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->label('Orden visual')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Toggle::make('is_primary')
                            ->label('Foto principal')
                            ->default(false)
                            ->helperText('Solo puede haber una foto principal por equipo'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_name')
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Vista previa')
                    ->disk('public')
                    ->height(60)
                    ->width(80)
                    ->defaultImageUrl(asset('images/no-photo.svg')),
                TextColumn::make('file_name')
                    ->label('Archivo')
                    ->searchable()
                    ->limit(35),
                TextColumn::make('caption')
                    ->label('Leyenda')
                    ->searchable()
                    ->limit(40)
                    ->placeholder('Sin leyenda'),
                IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
                TextColumn::make('uploadedBy.name')
                    ->label('Subida por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data, self $livewire): array {
                        $data['uploaded_by'] = auth()->id();

                        if (! empty($data['file_path'])) {
                            $disk = Storage::disk('public');
                            if ($disk->exists($data['file_path'])) {
                                $data['file_size'] = $disk->size($data['file_path']);
                                $data['mime_type'] = $disk->mimeType($data['file_path']) ?: null;
                            }
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('set_primary')
                    ->label('Establecer principal')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->hidden(fn (EquipmentPhoto $record): bool => $record->is_primary)
                    ->requiresConfirmation()
                    ->action(fn (EquipmentPhoto $record) => $record->update(['is_primary' => true])),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
