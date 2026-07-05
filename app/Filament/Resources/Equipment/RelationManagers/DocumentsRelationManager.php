<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Domain\Assets\Enums\DocumentType;
use App\Models\EquipmentDocument;
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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->documents()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Archivo')
                    ->columns(2)
                    ->schema([
                        Select::make('document_type')
                            ->label('Tipo de documento')
                            ->options(DocumentType::options())
                            ->required(),
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('file_path')
                            ->label('Archivo')
                            ->required()
                            ->disk(persistent_disk())
                            ->directory(fn ($livewire) => 'equipment-documents/'.$livewire->ownerRecord->tenant_id.'/'.$livewire->ownerRecord->id)
                            ->storeFileNamesIn('file_name')
                            ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
                            ->preventFilePathTampering()
                            ->maxSize(20480) // 20 MB
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->columnSpanFull(),
                        TextInput::make('version')
                            ->label('Versión')
                            ->maxLength(50)
                            ->default('v1.0')
                            ->placeholder('v1.0'),
                        DatePicker::make('expires_at')
                            ->label('Fecha de vencimiento')
                            ->displayFormat('d/m/Y'),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('file_name')
                    ->label('Archivo')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('document_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (DocumentType $state): string => $state->color())
                    ->formatStateUsing(fn (DocumentType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('version')
                    ->label('Versión')
                    ->placeholder('—'),
                TextColumn::make('expires_at')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->color(fn (EquipmentDocument $record): string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->isExpiringSoon() => 'warning',
                        default => 'success',
                    })
                    ->placeholder('Sin vencimiento')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('uploadedBy.name')
                    ->label('Cargado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha carga')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('document_type')
                    ->label('Tipo')
                    ->options(DocumentType::options()),
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->tooltip('Subir un documento nuevo para este equipo')
                    ->mutateFormDataUsing(function (array $data, self $livewire): array {
                        $data['uploaded_by'] = auth()->id();

                        if (! empty($data['file_path'])) {
                            $disk = Storage::disk(persistent_disk());
                            if ($disk->exists($data['file_path'])) {
                                $data['file_size'] = $disk->size($data['file_path']);
                                $data['mime_type'] = $disk->mimeType($data['file_path']) ?: null;
                            }
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->tooltip('Descargar este documento')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn (EquipmentDocument $record): ?string => file_signed_url(persistent_disk(), $record->file_path))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->tooltip('Editar los datos del documento'),
                DeleteAction::make()
                    ->tooltip('Eliminar este documento'),
                RestoreAction::make()
                    ->tooltip('Recuperar este documento eliminado'),
                ForceDeleteAction::make()
                    ->tooltip('Eliminar definitivamente — no se puede recuperar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
