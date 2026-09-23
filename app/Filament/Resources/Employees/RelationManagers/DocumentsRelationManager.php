<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Models\EmployeeDocument;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

    /** @var list<string> */
    private const ACCEPTED_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

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
                ->acceptedFileTypes(self::ACCEPTED_TYPES)
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
            // El checklist va como descripción y no como cabecera propia: `header()`
            // reemplaza el bloque entero de la cabecera, y con él desaparecía el botón de
            // subir documentos. La carpeta se veía, pero no se podía llenar.
            ->description(fn (): View => view('filament.resources.employees.documents-checklist', [
                'employee' => $this->getOwnerRecord()->load('documents:id,employee_id,document_type'),
            ]))
            ->emptyStateHeading('La carpeta está vacía')
            ->emptyStateDescription('Suba la hoja de vida, la cédula y los demás documentos obligatorios.')
            ->emptyStateIcon(Heroicon::OutlinedFolderOpen)
            ->emptyStateActions([$this->uploadAction()])
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
            ->headerActions([$this->uploadAction()])
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
     * Subir uno o varios archivos de una vez.
     *
     * Varios, porque un documento en papel casi nunca es una hoja: la cédula tiene dos
     * caras, los exámenes médicos son tres o cuatro informes y el contrato viene con sus
     * anexos. Cada archivo queda como su propia fila —así se descarga y se reemplaza por
     * separado— pero comparten tipo, descripción y vencimiento, que se escriben una vez.
     *
     * No es un `CreateAction` porque ese crea exactamente un registro.
     */
    private function uploadAction(): Action
    {
        return Action::make('subir')
            ->label('Subir documentos')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Subir documentos a la carpeta')
            ->modalSubmitActionLabel('Subir')
            ->authorize(fn (): bool => auth()->user()?->can('create', EmployeeDocument::class) ?? false)
            ->fillForm(fn (): array => [
                // Propone el primero que falta: quien completa carpetas los sube en ese
                // orden, y así no busca el tipo en la lista cada vez.
                'document_type' => ($this->getOwnerRecord()->load('documents')->missingRequiredDocuments()[0]
                    ?? EmployeeDocumentType::Otro)->value,
            ])
            ->schema([
                Select::make('document_type')
                    ->label('Tipo de documento')
                    ->options(EmployeeDocumentType::options())
                    ->required()
                    ->native(false),
                TextInput::make('title')
                    ->label('Descripción')
                    ->placeholder('Opcional. Si la deja vacía se usa el tipo de documento.')
                    ->maxLength(255),
                FileUpload::make('files')
                    ->label('Archivos')
                    ->helperText('Puede subir varios a la vez: cada uno queda como un documento aparte.')
                    ->required()
                    ->multiple()
                    ->maxFiles(10)
                    ->disk(private_files_disk())
                    ->visibility('private')
                    ->directory(fn (): string => 'employee-documents/'
                        .$this->getOwnerRecord()->tenant_id.'/'.$this->getOwnerRecord()->id)
                    ->storeFileNamesIn('file_names')
                    ->preventFilePathTampering()
                    ->maxSize(20480)
                    ->acceptedFileTypes(self::ACCEPTED_TYPES)
                    ->columnSpanFull(),
                DatePicker::make('expires_at')
                    ->label('Vence')
                    ->helperText('Para exámenes médicos, cursos de alturas y afiliaciones. Déjelo vacío si no vence.'),
                Textarea::make('notes')->label('Notas')->rows(2)->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $names = (array) ($data['file_names'] ?? []);
                $employee = $this->getOwnerRecord();
                $created = 0;

                foreach ((array) $data['files'] as $path) {
                    $employee->documents()->create([
                        'tenant_id' => $employee->tenant_id,
                        'document_type' => $data['document_type'],
                        'title' => $data['title'] ?: null,
                        'file_path' => $path,
                        'file_name' => $names[$path] ?? basename($path),
                        'expires_at' => $data['expires_at'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'uploaded_by' => auth()->id(),
                        ...self::fileMetadata($path),
                    ]);

                    $created++;
                }

                Notification::make()
                    ->title($created === 1 ? 'Documento subido' : $created.' documentos subidos')
                    ->success()
                    ->send();
            });
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
