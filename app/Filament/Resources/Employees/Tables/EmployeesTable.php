<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Services\EmployeeQrCodeService;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->getStateUsing(fn (Employee $record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name']),

                TextColumn::make('position')
                    ->label('Cargo')
                    ->limitWithTooltip(30)
                    ->toggleable(),

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

                TernaryFilter::make('excluded_from_overtime')
                    ->label('No causa horas extras'),
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
