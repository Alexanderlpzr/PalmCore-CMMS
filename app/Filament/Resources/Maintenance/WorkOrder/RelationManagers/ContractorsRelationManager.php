<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Contractor;
use App\Models\WorkOrderContractor;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Los terceros que ejecutan esta OT.
 *
 * Todo pasa por {@see WorkOrderService}: la asignación recalcula el costo externo
 * de la OT, y quitarla se lo lleva. Escribir la fila a mano desde aquí dejaría el
 * costo desincronizado.
 */
class ContractorsRelationManager extends RelationManager
{
    protected static string $relationship = 'contractors';

    protected static ?string $title = 'Contratistas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('contractor_id')
                ->label('Contratista')
                ->options(fn (): array => Contractor::query()
                    ->active()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),
            TextInput::make('agreed_cost')
                ->label('Costo pactado')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->helperText('Se congela en esta OT. Cambiar la tarifa del contratista después no lo altera.'),
            TextInput::make('invoice_number')
                ->label('N.º de factura')
                ->maxLength(60),
            Textarea::make('scope')
                ->label('Alcance contratado')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contractor.name')
                    ->label('Contratista')
                    ->searchable(),
                TextColumn::make('contractor.specialty')
                    ->label('Especialidad')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('scope')
                    ->label('Alcance')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('agreed_cost')
                    ->label('Costo pactado')
                    ->money(fn (WorkOrderContractor $record): string => $record->currency_code ?? 'COP')
                    ->placeholder('Sin factura'),
                TextColumn::make('invoice_number')
                    ->label('Factura')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar contratista')
                    ->using(fn (array $data, WorkOrderService $service): WorkOrderContractor => $service->assignContractor(
                        $this->getOwnerRecord(),
                        Contractor::findOrFail($data['contractor_id']),
                        agreedCost: isset($data['agreed_cost']) ? (float) $data['agreed_cost'] : null,
                        scope: $data['scope'] ?? null,
                        invoiceNumber: $data['invoice_number'] ?? null,
                    )),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->using(function (WorkOrderContractor $record, WorkOrderService $service): void {
                        $service->removeContractor($this->getOwnerRecord(), $record->contractor);
                    }),
            ]);
    }
}
