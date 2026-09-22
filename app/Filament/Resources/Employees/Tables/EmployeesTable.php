<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Services\EmployeeQrCodeService;
use App\Domain\HumanResources\Support\EmployeeProfileOptions as Options;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            // «Carpeta» necesita el tipo de cada documento y «Carné» el QR activo. Sin
            // cargarlos aquí, cada fila los pedía por separado, y con la carga perezosa
            // prohibida fuera de producción la lista ni siquiera abría.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'documents:id,employee_id,document_type',
                'qrCode',
            ]))
            // Cargo | Código | Nombre | Documento: así lo lee talento humano, que busca
            // primero por puesto —«¿quién está de operario de proceso?»— y después por
            // persona. El documento es la llave, pero casi nunca es lo primero que se mira.
            ->columns([
                TextColumn::make('position')
                    ->label('Cargo')
                    ->description(fn (Employee $record): ?string => Options::SPECIFIC_AREAS[$record->area_specific] ?? null)
                    ->searchable()
                    ->sortable()
                    ->limitWithTooltip(30),

                TextColumn::make('employee_code')
                    ->label('Código')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->getStateUsing(fn (Employee $record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name']),

                TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('phone')
                    ->label('Celular')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EmploymentStatus $state): string => $state->label())
                    ->color(fn (EmploymentStatus $state): string => $state->color()),

                // La columna que más se va a mirar cuando algo no cuadre en las horas.
                IconColumn::make('excluded_from_overtime')
                    ->label('Sin extras')
                    ->boolean()
                    ->tooltip('Dirección, confianza y manejo: no causa horas extras ni recargos.')
                    ->toggleable(),

                TextColumn::make('base_salary')
                    ->label('Salario básico')
                    ->money('COP', 0)
                    ->alignEnd()
                    ->sortable()
                    // El sueldo solo lo ve quien tiene ese permiso, aparte de `employees.view`.
                    // `viewAnySalary` y no `viewSalary`: aquí no hay un empleado concreto.
                    ->visible(fn (): bool => auth()->user()?->can('viewAnySalary', Employee::class) ?? false)
                    ->toggleable(),

                // Cuántos de los once documentos obligatorios tiene la carpeta. Solo lo ve
                // quien puede abrirla: a portería no le dice nada.
                TextColumn::make('documents_progress')
                    ->label('Carpeta')
                    ->badge()
                    ->getStateUsing(fn (Employee $record): string => $record->requiredDocumentsProgress())
                    ->color(fn (Employee $record): string => match (count($record->missingRequiredDocuments())) {
                        0 => 'success',
                        count(EmployeeDocumentType::required()) => 'danger',
                        default => 'warning',
                    })
                    ->tooltip(fn (Employee $record): ?string => ($missing = $record->missingRequiredDocuments())
                        ? 'Faltan: '.collect($missing)->map->shortLabel()->implode(', ')
                        : null)
                    ->visible(fn (): bool => auth()->user()?->can('create', EmployeeDocument::class) ?? false)
                    ->toggleable(),

                IconColumn::make('qrCode')
                    ->label('Carné')
                    ->boolean()
                    ->getStateUsing(fn (Employee $record): bool => $record->qrCode !== null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EmploymentStatus::options())
                    ->default(EmploymentStatus::Activo->value),

                SelectFilter::make('area_specific')
                    ->label('Área específica')
                    ->options(Options::SPECIFIC_AREAS),

                TernaryFilter::make('excluded_from_overtime')
                    ->label('No causa horas extras'),

                // Por tipos distintos y no por filas: dos exámenes médicos no reemplazan
                // al contrato que falta.
                Filter::make('carpeta_incompleta')
                    ->label('Carpeta incompleta')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(
                        DB::table('hr_employee_documents')
                            ->selectRaw('count(distinct document_type)')
                            ->whereColumn('employee_id', 'hr_employees.id')
                            ->whereNull('deleted_at')
                            ->whereIn('document_type', array_map(
                                fn (EmployeeDocumentType $type): string => $type->value,
                                EmployeeDocumentType::required(),
                            )),
                        '<',
                        count(EmployeeDocumentType::required()),
                    )),

                // Los cumpleaños del mes: el Excel tenía dos columnas, DÍA y MES, solo
                // para poder filtrar esto.
                Filter::make('cumple_este_mes')
                    ->label('Cumple años este mes')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereMonth('birth_date', now()->month)),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('reemitirCarne')
                    ->label('Reemitir carné')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->authorize(fn (Employee $record): bool => auth()->user()?->can('manageQrCode', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Reemitir el carné')
                    ->modalDescription(
                        'El carné anterior deja de servir de inmediato: mientras siga activo, '
                        .'quien lo encuentre puede marcarle la entrada a su dueño.'
                    )
                    ->modalSubmitActionLabel('Reemitir')
                    ->action(function (Employee $record): void {
                        $service = app(EmployeeQrCodeService::class);
                        $current = $record->qrCode;

                        $current
                            ? $service->regenerate($current)
                            : $service->createForEmployee($record);

                        Notification::make()
                            ->title('Carné reemitido')
                            ->body('El anterior quedó anulado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
