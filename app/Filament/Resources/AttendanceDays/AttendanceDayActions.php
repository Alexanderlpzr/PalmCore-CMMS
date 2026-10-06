<?php

namespace App\Filament\Resources\AttendanceDays;

use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Filament\Resources\AttendanceScans\AttendanceMarkActions;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;

/**
 * Corregir un día desde «Horas por confirmar»: la hora de sus marcas, sus horas a mano o
 * anularlo. Las tres piden motivo y solo sirven sobre un día sin firmar; uno confirmado
 * se reabre antes.
 */
final class AttendanceDayActions
{
    /** Las ocho bolsas, con el nombre con que se leen en la planilla. */
    private const HOUR_LABELS = [
        'ordinary_hours' => 'Ordinarias',
        'night_surcharge_hours' => 'Recargo nocturno',
        'sunday_surcharge_hours' => 'Recargo dominical',
        'night_sunday_surcharge_hours' => 'Recargo nocturno dominical',
        'overtime_day_hours' => 'Extra diurna',
        'overtime_night_hours' => 'Extra nocturna',
        'overtime_sunday_day_hours' => 'Extra dominical diurna',
        'overtime_sunday_night_hours' => 'Extra dominical nocturna',
    ];

    public static function editMarks(): Action
    {
        return Action::make('editarMarcas')
            ->label('Editar marcas')
            ->icon(Heroicon::OutlinedClock)
            ->authorize(fn (AttendanceDay $record): bool => auth()->user()?->can('correct', $record) ?? false)
            ->modalHeading(fn (AttendanceDay $record): string => 'Corregir las marcas de '.$record->employee?->fullName().' del '.$record->work_date->format('d/m/Y'))
            ->modalDescription('Cambie la hora que esté mal. La marca vieja queda anulada con el motivo y la nueva entra a mano; las horas del día se recalculan solas.')
            ->fillForm(fn (AttendanceDay $record): array => [
                'marcas' => self::service()->marksOfDay($record)
                    ->mapWithKeys(fn (AttendanceScan $marca): array => [$marca->getKey() => $marca->scanned_at->copy()->utc()->toDateTimeString()])
                    ->all(),
            ])
            ->schema(fn (AttendanceDay $record): array => [
                ...self::service()->marksOfDay($record)->isEmpty()
                    ? [Text::make('Este día no tiene marcas de la puerta. Use «Agregar marca» para registrar la entrada o la salida.')]
                    : self::service()->marksOfDay($record)->map(fn (AttendanceScan $marca): DateTimePicker => DateTimePicker::make('marcas.'.$marca->getKey())
                        ->label($marca->direction->label().($marca->source === 'manual' ? ' (a mano)' : ''))
                        ->timezone(AttendanceMarkActions::timezone())
                        ->seconds(false)
                        ->native(false)
                        ->displayFormat('d/m/Y h:i a')
                        ->maxDate(now())
                        ->required())->all(),
                Textarea::make('reason')
                    ->label('Motivo')
                    ->helperText('Queda escrito en la marca anulada y en la nueva.')
                    ->required()
                    ->minLength(5)
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Guardar las horas')
            ->action(function (AttendanceDay $record, array $data, Action $action): void {
                self::run($action, fn () => self::service()->editDayMarks($record, $data['marcas'] ?? [], $data['reason'], self::actor()));

                Notification::make()->title('Marcas corregidas')->body('Las horas del día se recalcularon.')->success()->send();
            });
    }

    public static function adjustHours(): Action
    {
        return Action::make('ajustarHoras')
            ->label('Ajustar horas')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->authorize(fn (AttendanceDay $record): bool => auth()->user()?->can('correct', $record) ?? false)
            ->modalHeading(fn (AttendanceDay $record): string => 'Ajustar las horas de '.$record->employee?->fullName().' del '.$record->work_date->format('d/m/Y'))
            ->modalDescription('Para cambiar las horas sin tocar las marcas: una extra que no se autorizó, un turno que se paga distinto. El día queda «ajustado a mano» y el reloj ya no lo recalcula, salvo que después se corrijan sus marcas.')
            ->fillForm(fn (AttendanceDay $record): array => [
                ...collect(AttendanceCorrectionService::HOUR_FIELDS)
                    ->mapWithKeys(fn (string $campo): array => [$campo => round((float) $record->{$campo}, 2)])
                    ->all(),
                'reason' => $record->adjustment_reason,
            ])
            ->schema([
                Grid::make(2)->schema(
                    collect(self::HOUR_LABELS)
                        ->map(fn (string $etiqueta, string $campo): TextInput => TextInput::make($campo)
                            ->label($etiqueta)
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->step(0.25)
                            ->suffix('h')
                            ->required())
                        ->values()
                        ->all(),
                ),
                Textarea::make('reason')
                    ->label('Motivo')
                    ->helperText('Queda en el día, con su nombre y la fecha.')
                    ->required()
                    ->minLength(5)
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Guardar el ajuste')
            ->action(function (AttendanceDay $record, array $data, Action $action): void {
                self::run($action, fn () => self::service()->adjustHours($record, $data, $data['reason'], self::actor()));

                Notification::make()->title('Horas ajustadas a mano')->success()->send();
            });
    }

    public static function voidDay(): Action
    {
        return Action::make('anularDia')
            ->label('Anular día')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize(fn (AttendanceDay $record): bool => auth()->user()?->can('correct', $record) ?? false)
            ->modalHeading(fn (AttendanceDay $record): string => 'Anular el '.$record->work_date->format('d/m/Y').' de '.$record->employee?->fullName())
            ->modalDescription('El día sale de «Horas por confirmar», no se paga y no vuelve al reconstruir: sus marcas quedan anuladas. Se siguen viendo en «Marcas de portería», con su nombre y el motivo.')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->minLength(5)
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Anular el día')
            ->action(function (AttendanceDay $record, array $data, Action $action): void {
                self::run($action, fn () => self::service()->voidDay($record, $data['reason'], self::actor()));

                Notification::make()->title('Día anulado')->success()->send();
            });
    }

    /** Las reglas viven en el servicio y ya hablan español: aquí solo se muestran. */
    private static function run(Action $action, callable $operation): void
    {
        try {
            $operation();
        } catch (AttendanceException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $action->halt();
        }
    }

    private static function service(): AttendanceCorrectionService
    {
        return app(AttendanceCorrectionService::class);
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
