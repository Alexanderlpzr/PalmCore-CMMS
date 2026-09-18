<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Models\EmployeeDocument;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * La carpeta del trabajador, la pestaña «Documentos» de su ficha.
 *
 * Arriba de la tabla va el checklist de los once documentos obligatorios, y «Subir
 * documento» propone ya el primero que falta: quien completa carpetas los sube en ese
 * orden y así no tiene que buscar el tipo en la lista cada vez.
 *
 * Disco privado y descarga por controlador: ver la migración que creó la tabla.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedFolderOpen;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewDocuments', $ownerRecord) ?? false;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->documents()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('document_type')
                ->label('Tipo de documento')
                ->options(EmployeeDocumentType::options())
                ->required()
                ->native(false),
            TextInput::make('title')
                ->label('Descripción')
                ->placeholder('Opcional. Si la deja vacía se usa el tipo de documento.')
                ->maxLength(255),
            FileUpload::make('file_path')
                ->label('Archivo')
                ->required()
                ->disk(private_files_disk())
                ->visibility('private')
                ->directory(fn (self $livewire): string => 'employee-documents/'
                    .$livewire->ownerRecord->tenant_id.'/'.$livewire->ownerRecord->id)
                ->storeFileNamesIn('file_name')
                ->preventFilePathTampering()
                ->maxSize(20480)
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
                ->columnSpanFull(),
            DatePicker::make('expires_at')
                ->label('Vence')
                ->helperText('Para exámenes médicos, cursos de alturas y afiliaciones. Déjelo vacío si no vence.'),
            Textarea::make('notes')->label('Notas')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->header(fn (): View => view('filament.resources.employees.documents-checklist', [
                'employee' => $this->getOwnerRecord()->load('documents:id,employee_id,document_type'),
            ]))
            ->emptyStateHeading('La carpeta está vacía')
            ->emptyStateDescription('Suba la hoja de vida, la cédula y los demás documentos obligatorios.')
            ->emptyStateIcon(Heroicon::OutlinedFolderOpen)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Documento')
                    ->description(fn (EmployeeDocument $record): string => $record->file_name)
                    ->searchable()
                    ->limitWithTooltip(50),
                TextColumn::make('document_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (EmployeeDocumentType $state): string => $state->label())
                    ->color(fn (EmployeeDocumentType $state): string => $state->color()),
                TextColumn::make('expires_at')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->color(fn (EmployeeDocument $record): ?string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->isExpiringSoon() => 'warning',
                        default => null,
                    })
                    ->placeholder('No vence')
                    ->sortable(),
                TextColumn::make('uploadedBy.name')
                    ->label('Subido por')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('document_type')
                    ->label('Tipo')
                    ->options(EmployeeDocumentType::options()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Subir documento')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->modalHeading('Subir documento a la carpeta')
                    ->fillForm(fn (): array => [
                        'document_type' => ($this->getOwnerRecord()->load('documents')->missingRequiredDocuments()[0]
                            ?? EmployeeDocumentType::Otro)->value,
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        $data['tenant_id'] = Filament::getTenant()->id;
                        $data['uploaded_by'] = auth()->id();
                        $data['file_name'] ??= basename($data['file_path']);

                        return [...$data, ...self::fileMetadata($data['file_path'])];
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('info')
                    ->url(fn (EmployeeDocument $record): string => route('employee-documents.download', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * `file_size` y `mime_type` se leen del disco: el formulario no los trae.
     *
     * @return array{file_size: int, mime_type: string}
     */
    private static function fileMetadata(string $path): array
    {
        $disk = Storage::disk(private_files_disk());
        $exists = $disk->exists($path);

        return [
            'file_size' => $exists ? $disk->size($path) : 0,
            'mime_type' => ($exists ? $disk->mimeType($path) : null) ?: 'application/octet-stream',
        ];
    }
}
