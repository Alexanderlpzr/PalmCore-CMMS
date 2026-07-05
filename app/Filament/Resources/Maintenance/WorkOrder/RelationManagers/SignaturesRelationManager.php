<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SignaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'signatures';

    protected static ?string $title = 'Firmas de Conformidad';

    // Read-only audit trail — signatures are only ever created automatically
    // by WorkOrderService::addSignature() when a técnico completes the OT or
    // a supervisor verifies it. A manual "create signature" button here would
    // let anyone fabricate a signature of any type disconnected from the
    // actual completion/verification event.
    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Firma')
                    ->disk(private_files_disk())
                    ->height(50)
                    ->width(100)
                    ->defaultImageUrl(asset('images/no-photo.svg')),
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
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
