<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
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
            TextInput::make('attachment_label')
                ->label('Etiqueta del adjunto')
                ->required()
                ->maxLength(255)
                ->placeholder('ej: Manual SKF, Plano eléctrico'),
            FileUpload::make('file_path')
                ->label('Archivo')
                ->required()
                ->disk(persistent_disk())
                ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
                ->directory('maintenance-plan-attachments')
                ->preserveFilenames()
                ->maxSize(20480)
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('attachment_label')
                    ->label('Etiqueta')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('file_name')
                    ->label('Archivo')
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('mime_type')
                    ->label('Tipo')
                    ->limit(30)
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
                        $data['tenant_id'] = $this->getOwnerRecord()->tenant_id;
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
