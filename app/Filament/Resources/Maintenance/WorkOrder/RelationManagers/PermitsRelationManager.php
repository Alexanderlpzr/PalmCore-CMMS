<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Enums\WorkPermitStatus;
use App\Domain\Maintenance\Enums\WorkPermitType;
use App\Domain\Maintenance\Services\WorkPermitService;
use App\Models\User;
use App\Models\WorkPermit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Permisos de alto riesgo de esta OT.
 *
 * Emitir y firmar son dos acciones distintas y de dos personas distintas — el
 * servicio lo exige. La UI no puede ofrecer un atajo que la regla prohíbe.
 */
class PermitsRelationManager extends RelationManager
{
    protected static string $relationship = 'permits';

    protected static ?string $title = 'Permisos de trabajo';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('permit_type')
                ->label('Tipo de permiso')
                ->options(WorkPermitType::options())
                ->required()
                ->live(),
            DateTimePicker::make('valid_from')
                ->label('Vigente desde')
                ->required()
                ->default(now()),
            DateTimePicker::make('valid_until')
                ->label('Vigente hasta')
                ->required()
                ->after('valid_from')
                ->default(now()->addHours(8))
                ->helperText('Un permiso «del día» no tiene vigencia. La hora importa.'),
            Textarea::make('hazards')
                ->label('Peligros identificados (ATS)')
                ->rows(3)
                ->required()
                ->columnSpanFull(),
            Textarea::make('controls')
                ->label('Controles')
                ->rows(3)
                ->required()
                ->columnSpanFull(),
            Repeater::make('isolation_points')
                ->label('Puntos de aislamiento (bloqueo y etiquetado)')
                ->schema([
                    TextInput::make('point')
                        ->label('Punto')
                        ->required()
                        ->placeholder('Breaker CCM-04 bloqueado con candado rojo'),
                ])
                ->visible(fn ($get): bool => WorkPermitType::tryFrom((string) $get('permit_type'))?->requiresIsolation() ?? false)
                ->required(fn ($get): bool => WorkPermitType::tryFrom((string) $get('permit_type'))?->requiresIsolation() ?? false)
                ->helperText('Sin bloqueo, el equipo sigue energizado con alguien adentro.')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('permit_number')
                    ->label('N.º')
                    ->searchable(),
                TextColumn::make('permit_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (WorkPermitType $state): string => $state->label()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (WorkPermitStatus $state): string => $state->label())
                    ->color(fn (WorkPermitStatus $state): string => $state->color()),
                TextColumn::make('valid_until')
                    ->label('Vence')
                    ->dateTime('d/m/Y H:i')
                    ->color(fn (WorkPermit $record): string => $record->isExpired() ? 'danger' : 'gray'),
                TextColumn::make('issuedBy.name')
                    ->label('Emitido por'),
                TextColumn::make('acceptedBy.name')
                    ->label('Firmado por')
                    ->placeholder('Sin firmar'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Emitir permiso')
                    ->using(fn (array $data, WorkPermitService $service): WorkPermit => $service->issue(
                        $this->getOwnerRecord(),
                        WorkPermitType::from($data['permit_type']),
                        [
                            ...$data,
                            'isolation_points' => array_column($data['isolation_points'] ?? [], 'point'),
                        ],
                        auth()->user(),
                    )),
            ])
            ->recordActions([
                // Firmar no es emitir: quien recibe el permiso es otra persona.
                Action::make('accept')
                    ->label('Firmar como ejecutante')
                    ->icon('heroicon-o-hand-raised')
                    ->color('success')
                    ->visible(fn (WorkPermit $record): bool => $record->status === WorkPermitStatus::Issued)
                    ->schema([
                        Select::make('accepted_by')
                            ->label('Ejecutante que recibe el permiso')
                            ->options(User::query()->operationalStaff()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(fn (WorkPermit $record, array $data, WorkPermitService $service) => $service->accept(
                        $record,
                        User::findOrFail($data['accepted_by']),
                    )),
                Action::make('close')
                    ->label('Cerrar')
                    ->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->modalDescription('Cerrar el permiso significa que se retiraron los candados y el equipo puede volver a energizarse.')
                    ->visible(fn (WorkPermit $record): bool => in_array(
                        $record->status,
                        [WorkPermitStatus::Issued, WorkPermitStatus::Accepted],
                        strict: true,
                    ))
                    ->action(fn (WorkPermit $record, WorkPermitService $service) => $service->close($record, auth()->user())),
            ]);
    }
}
