<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\AttendanceScans\AttendanceMarkActions;
use App\Filament\Resources\Employees\EmployeeQrActions;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * El carné del trabajador: el vigente, con su QR, y los que tuvo antes.
 *
 * Es el único sitio donde se reemite, lejos de «Descargar QR» en la lista de Personal.
 * De cada carné anulado se ve quién lo anuló, cuándo y por qué; de cada uno, cuándo se
 * emitió, cuándo se usó por última vez y cuántas marcas hizo.
 */
class QrCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'qrCodes';

    protected static ?string $title = 'Carné';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedQrCode;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('manageQrCode', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        // En hora de la planta: desde las 7 p. m. de Colombia, en UTC ya es el día siguiente.
        $timezone = AttendanceMarkActions::timezone();

        return $table
            // Con los anulados: son el historial del carné.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withTrashed()->with('revokedBy:id,name'))
            ->defaultSort('generated_at', 'desc')
            ->emptyStateHeading('Sin carné')
            ->emptyStateDescription('Este trabajador todavía no tiene carné. Emítalo para que pueda marcar en la puerta.')
            ->columns([
                ImageColumn::make('qr')
                    ->label('QR')
                    ->getStateUsing(fn (EmployeeQrCode $record): ?string => $record->trashed() ? null : $record->imageUrl())
                    ->square()
                    ->imageSize(72),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(fn (EmployeeQrCode $record): string => $record->trashed() ? 'Anulado' : 'Vigente')
                    ->color(fn (string $state): string => $state === 'Vigente' ? 'success' : 'gray'),
                TextColumn::make('generated_at')
                    ->label('Emitido')
                    ->date('d/m/Y', $timezone)
                    ->placeholder('—'),
                TextColumn::make('last_scanned_at')
                    ->label('Último uso')
                    ->since($timezone)
                    ->dateTimeTooltip('d/m/Y H:i', $timezone)
                    ->placeholder('Nunca'),
                TextColumn::make('scan_count')
                    ->label('Marcas')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('revocation_reason')
                    ->label('Anulado')
                    ->getStateUsing(fn (EmployeeQrCode $record): ?string => $record->trashed()
                        ? ($record->revocation_reason?->label() ?? 'Sin motivo registrado')
                        : null)
                    ->description(fn (EmployeeQrCode $record): ?string => $record->trashed()
                        ? collect([
                            $record->revokedBy?->name,
                            $record->revoked_at?->copy()->setTimezone($timezone)->format('d/m/Y'),
                        ])->filter()->implode(' · ') ?: null
                        : null)
                    ->tooltip(fn (EmployeeQrCode $record): ?string => $record->revocation_detail)
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('descargarQr')
                    ->label('Descargar QR')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->authorize(fn (): bool => auth()->user()?->can('manageQrCode', $this->employee()) ?? false)
                    ->visible(fn (): bool => $this->employee()->qrCode !== null)
                    ->action(fn () => EmployeeQrActions::downloadFor($this->employee())),
                EmployeeQrActions::issue(fn (): Employee => $this->employee()),
                EmployeeQrActions::reissue(fn (): Employee => $this->employee()),
            ]);
    }

    private function employee(): Employee
    {
        /** @var Employee */
        return $this->getOwnerRecord();
    }
}
