<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\Reports\Excel\FormatoHorasExtrasExcel;
use App\Domain\Reports\Services\FormatoHorasExtrasPdfService as Formato;
use App\Filament\Resources\AttendanceDays\AttendanceDayActions;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El formato de horas extras (TH-FOR-002) del trabajador, en pantalla: una fila por día que
 * marcó en el corte, con la entrada, el fin del horario, la salida y las horas repartidas
 * como las liquida la nómina. Desde aquí se descarga en PDF o en Excel para firmar.
 *
 * Solo muestra horas; los valores, que dependen del salario, van en las descargas para
 * quien ve sueldos.
 */
class OvertimeRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceDays';

    protected static ?string $title = 'Horas extras';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClock;

    /** @var array<string, array<string, array<string, mixed>>> filas del formato por ventana */
    private array $formato = [];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', AttendanceDay::class) ?? false;
    }

    /** No se crean días a mano aquí, pero cada día se ajusta o se anula. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $horas = fn (string $bolsa, string $parte = 'legal'): \Closure => fn (AttendanceDay $record): ?float => ($v = $this->row($record)[$parte][$bolsa] ?? 0) > 0 ? $v : null;

        $columnas = [];

        foreach (Formato::COLUMNS as $bolsa => $sigla) {
            $columnas[] = TextColumn::make("formato_{$bolsa}")
                ->label($sigla)
                ->tooltip(Formato::COLUMN_TITLES[$bolsa])
                ->getStateUsing($horas($bolsa))
                ->numeric(2)
                ->placeholder('')
                ->alignEnd();
        }

        return $table
            ->defaultSort('work_date')
            ->paginated(false)
            ->description(fn (): string => $this->summary())
            ->emptyStateHeading('Sin horas en el periodo')
            ->emptyStateDescription('El trabajador no marcó en la puerta en estas fechas.')
            ->columns([
                TextColumn::make('work_date')
                    ->label('Fecha')
                    ->formatStateUsing(fn ($state): string => ucfirst(CarbonImmutable::parse($state)->locale('es')->translatedFormat('l j \d\e F')))
                    ->color(fn (AttendanceDay $record): ?string => ($this->row($record)['surcharged'] ?? false) ? 'danger' : null),
                TextColumn::make('inicio')
                    ->label('Hora inicio')
                    ->getStateUsing(fn (AttendanceDay $record): ?string => $this->row($record)['entry']?->format('g:i a')),
                TextColumn::make('fin_horario')
                    ->label('Fin horario laboral')
                    ->getStateUsing(fn (AttendanceDay $record): ?string => $this->row($record)['scheduledEnd']?->format('g:i a')),
                TextColumn::make('salida')
                    ->label('Hora salida')
                    ->getStateUsing(fn (AttendanceDay $record): ?string => $this->row($record)['exit']?->format('g:i a')),
                ...$columnas,
                TextColumn::make('bono')
                    ->label('Bono')
                    ->getStateUsing(fn (AttendanceDay $record): ?float => ($v = array_sum($this->row($record)['bonus'] ?? [])) > 0 ? $v : null)
                    ->tooltip(fn (AttendanceDay $record): ?string => collect($this->row($record)['bonus'] ?? [])
                        ->filter(fn (float $h): bool => $h > 0)
                        ->map(fn (float $h, string $bolsa): string => Formato::COLUMNS[$bolsa].' '.rtrim(rtrim(number_format($h, 2, ',', '.'), '0'), ','))
                        ->implode(' · ') ?: null)
                    ->numeric(2)
                    ->placeholder('')
                    ->alignEnd(),
                TextColumn::make('justificacion')
                    ->label('Justificación')
                    ->getStateUsing(fn (AttendanceDay $record): ?string => $this->row($record)['notes'] ?: null)
                    ->limit(40)
                    ->tooltip(fn (AttendanceDay $record): ?string => $this->row($record)['notes'] ?: null)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options(fn (): array => $this->windowOptions())
                    ->default(fn (): string => array_key_first($this->windowOptions()))
                    ->selectablePlaceholder(false)
                    ->query(function (Builder $query, array $data): Builder {
                        [$from, $to] = $this->window($data['value'] ?? null);

                        return $query->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]);
                    }),
            ])
            // Una novedad que llegó tarde: ajustar sus horas o anular el día, con motivo.
            ->recordActions([
                AttendanceDayActions::adjustHours(),
                AttendanceDayActions::voidDay(),
            ])
            ->headerActions([
                Action::make('formatoPdf')
                    ->label('Descargar PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->action(function (): StreamedResponse {
                        [$from, $to] = $this->window();
                        $service = app(Formato::class);

                        return response()->streamDownload(
                            fn () => print $service->generate($this->employee(), $from, $to, $this->canSeeSalary()),
                            $service->filename($this->employee(), $to),
                        );
                    }),
                Action::make('formatoExcel')
                    ->label('Descargar Excel')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->color('gray')
                    ->action(function (): StreamedResponse {
                        [$from, $to] = $this->window();

                        return app(FormatoHorasExtrasExcel::class)->download(collect([$this->employee()]), $from, $to, $this->canSeeSalary());
                    }),
            ]);
    }

    /**
     * Las ventanas de corte de los últimos seis meses, la que corre primero.
     *
     * @return array<string, string> «2026-09-27|2026-10-26» => «27/09/2026 al 26/10/2026»
     */
    private function windowOptions(): array
    {
        return PayrollRun::recentHoursWindows(
            (int) app(PayrollParameterService::class)->valueOrDefault(PayrollParameter::HoursCutoffDay, CarbonImmutable::today(), $this->employee()->tenant_id),
        );
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} la ventana elegida en el filtro */
    private function window(?string $value = null): array
    {
        $value ??= $this->getTableFilterState('periodo')['value'] ?? null;
        $value = $value && str_contains($value, '|') ? $value : array_key_first($this->windowOptions());
        [$from, $to] = explode('|', $value);

        return [CarbonImmutable::parse($from), CarbonImmutable::parse($to)];
    }

    /** @return array<string, mixed> la fila del formato para ese día */
    private function row(AttendanceDay $day): array
    {
        [$from, $to] = $this->window();
        $key = $from->toDateString().'|'.$to->toDateString();

        $this->formato[$key] ??= collect(app(Formato::class)->data($this->employee(), $from, $to)['rows'])
            ->keyBy(fn (array $row): string => $row['date']->toDateString())
            ->all();

        return $this->formato[$key][$day->work_date->toDateString()] ?? [];
    }

    /** Los totales del periodo, en la descripción de la tabla. */
    private function summary(): string
    {
        [$from, $to] = $this->window();
        $data = app(Formato::class)->data($this->employee(), $from, $to);
        $partes = collect(Formato::COLUMNS)
            ->map(fn (string $sigla, string $bolsa): ?string => $data['legalTotals'][$bolsa] > 0 ? $sigla.' '.rtrim(rtrim(number_format($data['legalTotals'][$bolsa], 2, ',', '.'), '0'), ',') : null)
            ->filter();
        $bono = array_sum($data['bonusTotals']);

        return 'Horas del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y').'. '
            .($partes->isEmpty() ? 'Sin horas extras ni recargos.' : 'Total: '.$partes->implode(' · ').'.')
            .($bono > 0 ? ' Bonificación: '.rtrim(rtrim(number_format($bono, 2, ',', '.'), '0'), ',').' h.' : '');
    }

    private function canSeeSalary(): bool
    {
        return auth()->user()?->can('viewSalary', $this->employee()) ?? false;
    }

    private function employee(): Employee
    {
        /** @var Employee */
        return $this->getOwnerRecord();
    }
}
