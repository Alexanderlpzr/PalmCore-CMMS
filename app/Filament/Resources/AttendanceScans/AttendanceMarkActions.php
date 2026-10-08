<?php

namespace App\Filament\Resources\AttendanceScans;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Las dos correcciones de la puerta, iguales en «Marcas de portería» y en «Horas por
 * confirmar»: agregar la marca que faltó y anular la que sobró. Las dos piden motivo y
 * las dos dejan el día recalculado.
 */
final class AttendanceMarkActions
{
    /** La hora de la planta: así se escribe y se lee cada marca en pantalla. */
    public static function timezone(): string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant->plantTimezone() : 'America/Bogota';
    }

    public static function add(string $name = 'agregarMarca'): Action
    {
        return Action::make($name)
            ->label('Agregar marca')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->authorize(fn (): bool => auth()->user()?->can('correct', AttendanceScan::class) ?? false)
            ->modalHeading('Agregar una marca que no quedó')
            ->modalDescription('La salida olvidada, la entrada sin carné. Queda como marca manual, con su nombre y el motivo, y el día se recalcula.')
            ->schema([
                Select::make('employee_id')
                    ->label('Trabajador')
                    ->options(fn (): array => Employee::query()
                        ->active()
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->fullName()])
                        ->all())
                    ->searchable()
                    ->required(),
                DateTimePicker::make('scanned_at')
                    ->label('Fecha y hora')
                    ->timezone(self::timezone())
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->maxDate(now())
                    ->required(),
                ToggleButtons::make('direction')
                    ->label('Sentido')
                    ->options(AttendanceDirection::options())
                    ->inline()
                    ->required(),
                Textarea::make('reason')
                    ->label('Justificación (opcional)')
                    ->helperText('Por qué no quedó: queda escrito junto a la marca.')
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Agregar marca')
            ->action(function (array $data, Action $action): void {
                $employee = Employee::query()->findOrFail($data['employee_id']);

                try {
                    app(AttendanceCorrectionService::class)->addManualMark(
                        $employee,
                        Carbon::parse($data['scanned_at']),
                        AttendanceDirection::from($data['direction']),
                        ($data['reason'] ?? ''),
                        self::actor(),
                    );
                } catch (AttendanceException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();
                }

                Notification::make()->title('Marca agregada')->body('El día se recalculó con ella.')->success()->send();
            });
    }

    public static function void(): Action
    {
        return Action::make('anular')
            ->label('Anular')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize(fn (): bool => auth()->user()?->can('correct', AttendanceScan::class) ?? false)
            ->visible(fn (AttendanceScan $record): bool => ! $record->isVoided())
            ->modalHeading(fn (AttendanceScan $record): string => sprintf(
                'Anular la %s de %s de las %s',
                mb_strtolower($record->direction->label()),
                $record->employee?->fullName() ?? 'este trabajador',
                $record->scanned_at->copy()->setTimezone(self::timezone())->format('d/m/Y H:i'),
            ))
            ->modalDescription('Deja de contar para las horas, pero no se borra: queda a la vista con su nombre y el motivo.')
            ->schema([
                Textarea::make('reason')
                    ->label('Justificación (opcional)')
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Anular marca')
            ->action(function (AttendanceScan $record, array $data, Action $action): void {
                try {
                    app(AttendanceCorrectionService::class)->voidMark($record, ($data['reason'] ?? ''), self::actor());
                } catch (AttendanceException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();
                }

                Notification::make()->title('Marca anulada')->body('El día se recalculó sin ella.')->success()->send();
            });
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
