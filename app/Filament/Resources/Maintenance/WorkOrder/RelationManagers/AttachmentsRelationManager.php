<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Enums\WorkOrderAttachmentType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Adjuntos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('attachment_type')
                ->label('Tipo')
                ->options(WorkOrderAttachmentType::options())
                ->required()
                ->default(WorkOrderAttachmentType::Evidence),
            FileUpload::make('file_path')
                ->label('Archivo')
                ->required()
                ->disk(private_files_disk())
                ->visibility('private')
                ->directory('work-order-attachments')
                ->preserveFilenames()
                ->maxSize(20480)
                ->acceptedFileTypes(['image/*', 'application/pdf', 'video/mp4'])
                ->columnSpanFull(),
            TextInput::make('caption')
                ->label('Descripción del archivo')
                ->maxLength(500)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('file_name')
                    ->label('Archivo')
                    ->limit(50),
                TextColumn::make('attachment_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (WorkOrderAttachmentType $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderAttachmentType $state): string => $state->label()),
                TextColumn::make('mime_type')
                    ->label('Formato')
                    ->limit(30)
                    ->placeholder('—'),
                TextColumn::make('caption')
                    ->label('Descripción')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('uploadedBy.name')
                    ->label('Subido por'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['uploaded_by'] = auth()->id();
                        $data['file_name'] = basename($data['file_path']);
                        $data['file_size'] = null;
                        $data['mime_type'] = null;

                        return $data;
                    }),
            ])
            ->actions([
                DeleteAction::make(),
            ]);
    }
}
