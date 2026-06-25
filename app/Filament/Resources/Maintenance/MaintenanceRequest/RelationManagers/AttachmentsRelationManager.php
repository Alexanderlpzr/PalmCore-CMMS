<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\RelationManagers;

use App\Models\MaintenanceRequestAttachment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Adjuntos';

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->attachments()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Archivo')
                            ->required()
                            ->disk(persistent_disk())
                            ->directory(fn ($livewire): string => 'maintenance-attachments/'.$livewire->ownerRecord->tenant_id.'/'.$livewire->ownerRecord->id)
                            ->storeFileNamesIn('file_name')
                            ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
                            ->preventFilePathTampering()
                            ->maxSize(20480)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ]),
                        TextInput::make('caption')
                            ->label('Descripción')
                            ->maxLength(500),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_name')
            ->columns([
                TextColumn::make('file_name')
                    ->label('Archivo')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('mime_type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('file_size')
                    ->label('Tamaño')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).' KB'),
                TextColumn::make('caption')
                    ->label('Descripción')
                    ->placeholder('—')
                    ->limit(40),
                TextColumn::make('uploadedBy.name')
                    ->label('Subido por'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['uploaded_by'] = auth()->id();
                        $data['tenant_id'] = $this->ownerRecord->tenant_id;

                        if (! empty($data['file_path'])) {
                            $disk = Storage::disk(persistent_disk());

                            if ($disk->exists($data['file_path'])) {
                                $data['file_size'] = $disk->size($data['file_path']);
                                $data['mime_type'] = $disk->mimeType($data['file_path']) ?: 'application/octet-stream';
                            }
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn (MaintenanceRequestAttachment $record): ?string => file_signed_url(persistent_disk(), $record->file_path))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
