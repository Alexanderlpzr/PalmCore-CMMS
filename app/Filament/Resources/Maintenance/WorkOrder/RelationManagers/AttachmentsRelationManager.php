<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Enums\WorkOrderAttachmentType;
use App\Models\WorkOrderAttachment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

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
                // Segmentado por tenant y por OT: con `preserveFilenames()` sobre un
                // directorio plano, dos tenants que suban «cotizacion.pdf» se pisaban
                // el archivo.
                ->directory(fn (self $livewire): string => 'work-order-attachments/'
                    .$livewire->ownerRecord->tenant_id.'/'.$livewire->ownerRecord->id)
                ->preserveFilenames()
                ->preventFilePathTampering()
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
                    ->limitWithTooltip(50),
                TextColumn::make('attachment_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (WorkOrderAttachmentType $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderAttachmentType $state): string => $state->label()),
                TextColumn::make('mime_type')
                    ->label('Formato')
                    ->limitWithTooltip(30)
                    ->placeholder('—'),
                TextColumn::make('caption')
                    ->label('Descripción')
                    ->limitWithTooltip(60)
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
                    ->tooltip('Adjuntar una foto, video o documento a esta OT')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Explícito, como en el resto de los relation managers: dejarlo
                        // al trait significaba depender de que `CurrentTenant` estuviera
                        // puesto por el middleware, y el insert falla en cuanto no lo está.
                        $data['tenant_id'] = Filament::getTenant()->id;
                        $data['uploaded_by'] = auth()->id();
                        $data['file_name'] = basename($data['file_path']);

                        // `file_size` y `mime_type` son NOT NULL. Escribirlas en null
                        // hacía que el insert fallara en Postgres, así que la pestaña
                        // no podía adjuntar nada: se leen del disco, como en los
                        // documentos de equipo y en los adjuntos de solicitud.
                        $disk = Storage::disk(private_files_disk());
                        $exists = $disk->exists($data['file_path']);

                        $data['file_size'] = $exists ? $disk->size($data['file_path']) : 0;
                        $data['mime_type'] = ($exists ? $disk->mimeType($data['file_path']) : null)
                            ?: 'application/octet-stream';

                        return $data;
                    }),
            ])
            ->actions([
                // Sin esto un adjunto es inalcanzable: el disco es privado y la tabla
                // no ofrecía ninguna forma de abrirlo. Un soporte que nadie puede abrir
                // no es trazabilidad.
                //
                // Va por una ruta propia y no por `file_signed_url()`: en producción el
                // disco privado es local y no expone URL, así que el enlace firmado
                // reventaría en vez de descargar.
                Action::make('download')
                    ->label('Descargar')
                    ->tooltip('Abrir este adjunto')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn (WorkOrderAttachment $record): string => route(
                        'work-order-attachments.download',
                        $record,
                    ))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->tooltip('Eliminar este adjunto'),
            ]);
    }
}
