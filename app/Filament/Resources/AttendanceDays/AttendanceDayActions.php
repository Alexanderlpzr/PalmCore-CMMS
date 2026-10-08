<?php

namespace App\Filament\Resources\AttendanceDays;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
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
                    ->label('Justificación (opcional)')
                    ->helperText('Queda escrito en la marca anulada y en la nueva.')
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Guardar las horas')
            ->action(function (AttendanceDay $record, array $data, Action $action): void {
                self::run($action, fn () => self::service()->editDayMarks($record, $data['marcas'] ?? [], ($data['reason'] ?? ''), self::actor()));

                Notification::make()->title('Marcas corregidas')->body('Las horas del día se recalcularon.')->success()->send();
            });
    }

    public static function adjustHours(): Action
    {
        return Action::make('ajustarHoras')
            ->label('Ajustar horas')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            // Sin firmar lo corrige quien confirma; firmado, también, y sigue firmado.
            ->authorize(fn (AttendanceDay $record): bool => self::canEdit($record))
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
                    ->label('Justificación (opcional)')
                    ->helperText('Queda en el día, con su nombre y la fecha.')
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Guardar el ajuste')
            ->action(function (AttendanceDay $record, array $data, Action $action): void {
                self::run($action, fn () => self::service()->adjustHours($record, $data, ($data['reason'] ?? ''), self::actor()));

                Notification::make()->title('Horas ajustadas a mano')->success()->send();
            });
    }

    public static function voidDay(): Action
    {
        return Action::make('anularDia')
            ->label('Anular día')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize(fn (AttendanceDay $record): bool => self::canEdit($record))
            ->modalHeading(fn (AttendanceDay $record): string => 'Anular el '.$record->work_date->format('d/m/Y').' de '.$record->employee?->fullName())
            ->modalDescription('El día se quita: no se paga y no vuelve al reconstruir, porque sus marcas quedan anuladas. Sirve también para un día ya confirmado que resultó ser una novedad. Las marcas se siguen viendo en «Marcas de portería», con su nombre y el motivo.')
            ->schema([
                Textarea::make('reason')
                    ->label('Justificación (opcional)')
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Anular el día')
            ->action(function (AttendanceDay $record, array $data, Action $action): void {
                self::run($action, fn () => self::service()->voidDay($record, ($data['reason'] ?? ''), self::actor()));

                Notification::make()->title('Día anulado')->success()->send();
            });
    }

    /**
     * El domingo o festivo que alguien vino a trabajar en su día de descanso. El reloj no
     * lo distingue de un domingo de turno; talento humano sí. Se prende y se apaga con
     * el mismo botón.
     */
    public static function restDayWorked(): Action
    {
        return Action::make('descansoTrabajado')
            ->label(fn (AttendanceDay $record): string => $record->rest_day_worked ? 'Quitar «descanso trabajado»' : 'Descanso trabajado')
            ->icon(Heroicon::OutlinedSun)
            ->authorize(fn (AttendanceDay $record): bool => auth()->user()?->can('correct', $record) ?? false)
            ->visible(fn (AttendanceDay $record): bool => $record->employee?->earnsOvertime() ?? false)
            ->requiresConfirmation()
            ->modalHeading(fn (AttendanceDay $record): string => ($record->rest_day_worked ? 'Quitar el descanso trabajado del ' : 'Descanso trabajado el ').$record->work_date->format('d/m/Y'))
            ->modalDescription(fn (AttendanceDay $record): string => $record->rest_day_worked
                ? 'El día vuelve a tener jornada ordinaria: sus primeras horas llevan solo el recargo y lo demás es extra.'
                : 'Para el domingo o festivo que vino a trabajar en su día libre: todo lo trabajado ese día se paga como extra, y el tope de horas extras por día no lo parte.')
            ->action(function (AttendanceDay $record, Action $action): void {
                $marcado = ! $record->rest_day_worked;

                self::run($action, fn () => self::service()->setRestDayWorked($record, $marcado));

                Notification::make()
                    ->title($marcado ? 'Día marcado como descanso trabajado' : 'El día volvió a tener jornada ordinaria')
                    ->body('Las horas del día se recalcularon.')
                    ->success()
                    ->send();
            });
    }

    /**
     * El operario de producción que ese día trabajó para mantenimiento. No cambia lo que
     * se le paga: cambia a qué grupo se cargan sus horas en el indicador «factor de horas».
     */
    public static function maintenanceSupport(): Action
    {
        return Action::make('apoyoMantenimiento')
            ->label(fn (AttendanceDay $record): string => $record->maintenance_support ? 'Quitar «apoyo a mantenimiento»' : 'Apoyo a mantenimiento')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->authorize(fn (AttendanceDay $record): bool => auth()->user()?->can('tag', $record) ?? false)
            ->action(function (AttendanceDay $record): void {
                $marcado = ! $record->maintenance_support;

                self::service()->setMaintenanceSupport($record, $marcado);

                Notification::make()
                    ->title($marcado ? 'Horas cargadas a «Apoyo a mantenimiento»' : 'Las horas volvieron a su área')
                    ->success()
                    ->send();
            });
    }

    /** Propuesto: `correct`. Confirmado: `editConfirmed`. */
    private static function canEdit(AttendanceDay $record): bool
    {
        return auth()->user()?->can($record->status === AttendanceDayStatus::Confirmada ? 'editConfirmed' : 'correct', $record) ?? false;
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
