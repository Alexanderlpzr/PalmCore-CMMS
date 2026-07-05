<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Services\WorkOrderService;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SignaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'signatures';

    protected static ?string $title = 'Firmas de Conformidad';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('signature_type')
                ->label('Tipo de firma')
                ->options(WorkOrderSignatureType::class)
                ->required(),
            Textarea::make('notes')
                ->label('Observaciones')
                ->rows(3)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('signature_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (WorkOrderSignatureType $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderSignatureType $state): string => $state->label()),
                TextColumn::make('user.name')
                    ->label('Firmante'),
                TextColumn::make('signed_at')
                    ->label('Fecha de firma')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('notes')
                    ->label('Observaciones')
                    ->limit(80)
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar firma')
                    ->using(function (array $data, WorkOrderService $service): mixed {
                        $type = $data['signature_type'] instanceof WorkOrderSignatureType
                            ? $data['signature_type']
                            : WorkOrderSignatureType::from($data['signature_type']);

                        return $service->addSignature(
                            $this->getOwnerRecord(),
                            auth()->user(),
                            $type,
                            $data['notes'] ?? null,
                        );
                    }),
            ])
            ->actions([]);
    }
}
