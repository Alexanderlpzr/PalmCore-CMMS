<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Domain\HumanResources\Services\OvertimeSummary;
use App\Domain\HumanResources\Services\OvertimeWorkbookImporter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\Reports\Excel\FormatoHorasExtrasExcel;
use App\Domain\Reports\Services\FormatoHorasExtrasPdfService as Formato;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\Hours;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * El formato de horas extras (TH-FOR-002) de todos los trabajadores, en un solo lugar: una
 * tabla con lo que cada uno lleva en el corte —extras, recargos, bonificación, días por
 * confirmar—, buscable, y desde cada fila su formato en pantalla, en PDF o en Excel.
 * Arriba, el Excel de todos con una hoja por trabajador, como el libro TH-NOM-P-001.
 *
 * La ve quien ve las horas de la puerta, como «Horas por confirmar». Los valores en pesos
 * solo salen en las descargas, y solo para quien ve sueldos.
 */
class HorasExtras extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano · Cierre del corte';

    protected static ?string $navigationLabel = 'Horas extras';

    protected static ?string $title = 'Horas extras';

    /** Justo después de «Horas por confirmar»: primero se confirma, después se mira. */
    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'horas-extras';

    protected string $view = 'filament.pages.horas-extras';

    /** @var array<string, array<string, array<string, mixed>>> totales por ventana */
    private array $totales = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', AttendanceDay::class) ?? false;
    }

    public function table(Table $table): Table
    {
        $horas = fn (string $campo): \Closure => fn (Employee $record): ?float => ($v = $this->totalsOf($record)[$campo] ?? 0) > 0 ? $v : null;
        $detalle = fn (array $bolsas, string $parte = 'legal'): \Closure => fn (Employee $record): ?string => collect($bolsas)
            ->map(fn (string $bolsa): ?string => ($h = $this->totalsOf($record)[$parte][$bolsa] ?? 0) > 0 ? Formato::COLUMNS[$bolsa].' '.$this->hours($h) : null)
            ->filter()
            ->implode(' · ') ?: null;

        return $table
            ->query(Employee::query()->active())
            ->defaultSort('first_name')
            ->description(fn (): string => 'Horas del '.$this->window()[0]->format('d/m/Y').' al '.$this->window()[1]->format('d/m/Y').'. Extras y recargos como horas extras; lo que pasa del tope diario y los días del mes anterior, como bonificación.')
            ->emptyStateHeading('Nadie marcó en este período')
            ->emptyStateDescription('Cambie el período o quite el filtro «Solo los que marcaron».')
            ->columns([
                TextColumn::make('employee_code')
                    ->label('Código')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('first_name')
                    ->label('Trabajador')
                    ->formatStateUsing(fn (Employee $record): string => $record->fullName())
                    ->description(fn (Employee $record): ?string => $record->document_number)
                    ->searchable(['first_name', 'last_name', 'document_number'])
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Cargo')
                    ->limit(28)
                    ->tooltip(fn (Employee $record): ?string => $record->position)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('immediate_supervisor')
                    ->label('Jefe inmediato')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dias')
                    ->label('Días')
                    ->getStateUsing(fn (Employee $record): int => $this->totalsOf($record)['days'] ?? 0)
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('extras')
                    ->label('Extras')
                    ->getStateUsing($horas('overtime'))
                    ->tooltip($detalle(['overtime_day', 'overtime_night', 'overtime_sunday_day', 'overtime_sunday_night']))
                    ->formatStateUsing(fn ($state): string => Hours::format($state))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('recargos')
                    ->label('Recargos')
                    ->getStateUsing($horas('surcharges'))
                    ->tooltip($detalle(OvertimeSummary::SURCHARGES))
                    ->formatStateUsing(fn ($state): string => Hours::format($state))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('bono')
                    ->label('Bonificación')
                    ->getStateUsing($horas('bonusHours'))
                    ->tooltip($detalle(array_keys(Formato::COLUMNS), 'bonus'))
                    ->formatStateUsing(fn ($state): string => Hours::format($state))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('sin_confirmar')
                    ->label('Por confirmar')
                    ->getStateUsing(fn (Employee $record): ?int => ($v = $this->totalsOf($record)['unconfirmed'] ?? 0) > 0 ? $v : null)
                    ->color('warning')
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('periodo')
                    ->label('Período')
                    ->options(fn (): array => $this->windowOptions())
                    ->default(fn (): ?string => array_key_first($this->windowOptions()))
                    ->selectablePlaceholder(false)
                    // Solo elige la ventana: qué filas salen lo decide el filtro de abajo.
                    ->query(fn (Builder $query): Builder => $query),
                Filter::make('solo_marcaron')
                    ->label('Solo los que marcaron')
                    ->toggle()
                    ->default()
                    ->query(function (Builder $query): Builder {
                        [$from, $to] = $this->window();

                        return $query->whereHas('attendanceDays', fn (Builder $days) => $days->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]));
                    }),
            ])
            ->recordActions([
                // Sus días del periodo, para ajustar horas o anular un día sin salir de aquí.
                Action::make('dias')
                    ->label('Días')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->modalHeading(fn (Employee $record): string => 'Días de '.$record->fullName())
                    ->modalContent(fn (Employee $record) => view('filament.pages.partials.dias-del-trabajador', ['employee' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl'),
                Action::make('verFormato')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->tooltip('El formato en la ficha del trabajador')
                    ->url(fn (Employee $record): string => EmployeeResource::getUrl('edit', ['record' => $record, 'relation' => 1])),
                // Todas sus horas del período, para volver a cargarlas (a mano o con el libro).
                Action::make('borrarHoras')
                    ->label('Borrar horas')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->authorize(fn (): bool => auth()->user()?->can('correct', AttendanceScan::class) ?? false)
                    ->modalHeading(fn (Employee $record): string => 'Borrar las horas de '.$record->fullName())
                    ->modalDescription(fn (): string => self::clearDescription($this->window()))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Justificación (opcional)')
                            ->rows(2),
                    ])
                    ->modalSubmitActionLabel('Borrar las horas')
                    ->action(function (Employee $record, array $data, Action $action): void {
                        $this->clearHours(collect([$record]), (string) ($data['reason'] ?? ''), $action);
                    }),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->action(function (Employee $record): StreamedResponse {
                        [$from, $to] = $this->window();
                        $service = app(Formato::class);

                        return response()->streamDownload(
                            fn () => print $service->generate($record, $from, $to, auth()->user()?->can('viewSalary', $record) ?? false),
                            $service->filename($record, $to),
                        );
                    }),
                Action::make('excel')
                    ->label('Excel')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->color('gray')
                    ->action(function (Employee $record): StreamedResponse {
                        [$from, $to] = $this->window();

                        return app(FormatoHorasExtrasExcel::class)->download(collect([$record]), $from, $to, auth()->user()?->can('viewSalary', $record) ?? false);
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('borrarHorasSeleccionados')
                    ->label('Borrar horas de los seleccionados')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->authorize(fn (): bool => auth()->user()?->can('correct', AttendanceScan::class) ?? false)
                    ->modalHeading('Borrar las horas de los trabajadores seleccionados')
                    ->modalDescription(fn (): string => self::clearDescription($this->window()))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Justificación (opcional)')
                            ->rows(2),
                    ])
                    ->modalSubmitActionLabel('Borrar las horas')
                    ->action(function (Collection $records, array $data, BulkAction $action): void {
                        $this->clearHours($records, (string) ($data['reason'] ?? ''), $action);
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->headerActions([
                // El libro TH-NOM-P-001 llena los días que la portería no registró.
                Action::make('importarLibro')
                    ->label('Importar libro de horas')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('gray')
                    ->authorize(fn (): bool => auth()->user()?->can('correct', AttendanceScan::class) ?? false)
                    ->modalHeading('Importar el libro de horas extras (TH-NOM-P-001)')
                    ->modalDescription('Entran la hora de entrada y de salida de cada día del libro, y las horas se calculan solas. Por omisión solo llena los días que la portería no registró; con «Reemplazar», el libro manda en los días que trae.')
                    ->schema([
                        FileUpload::make('libro')
                            ->label('Libro de Excel')
                            ->disk('local')
                            ->directory('importaciones-horas')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize(20480)
                            ->live()
                            ->required(),
                        Toggle::make('reemplazar')
                            ->label('Reemplazar lo que ya hay en esos días')
                            ->helperText('Las marcas de los días que trae el libro, de cada trabajador del libro, se anulan con el motivo «Reemplazada por el libro» y esos días —aun confirmados— se rehacen con el libro. Antes y después de esos días no se toca nada.')
                            ->live(),
                        Text::make(fn (Get $get): string => self::importPreview($get('libro'), (bool) $get('reemplazar'))),
                    ])
                    ->modalSubmitActionLabel('Importar')
                    ->action(function (array $data): void {
                        $path = Storage::disk('local')->path((string) $data['libro']);

                        try {
                            /** @var User $user */
                            $user = auth()->user();
                            $result = app(OvertimeWorkbookImporter::class)->import($path, Filament::getTenant(), $user, (bool) ($data['reemplazar'] ?? false));
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('No se importó el libro')->body($e->getMessage())->danger()->persistent()->send();

                            return;
                        } finally {
                            Storage::disk('local')->delete((string) $data['libro']);
                        }

                        Notification::make()
                            ->title($result['imported'] > 0 ? ($result['imported'] === 1 ? 'Se cargó 1 día' : "Se cargaron {$result['imported']} días").($result['workers'] === 1 ? ' de 1 trabajador' : " de {$result['workers']} trabajadores") : 'No había días para cargar')
                            ->body(($result['voided'] > 0 ? ($result['voided'] === 1 ? 'Se anuló 1 marca anterior. ' : "Se anularon {$result['voided']} marcas anteriores. ") : '').self::importSummary($result['counts'], $result['missingWorkers'], includeImport: false))
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                // Todos los de la tabla —con su búsqueda y sus filtros—, una hoja por trabajador.
                Action::make('excelTodos')
                    ->label('Descargar Excel de todos')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(function (): ?StreamedResponse {
                        [$from, $to] = $this->window();
                        $query = $this->getFilteredSortedTableQuery();

                        return $query
                            ? app(FormatoHorasExtrasExcel::class)->download($query->get(), $from, $to, auth()->user()?->can('viewAnySalary', Employee::class) ?? false)
                            : null;
                    }),
            ]);
    }

    /**
     * Lo que haría el libro recién subido, antes de importarlo.
     *
     * @param  mixed  $state  el estado del campo: el archivo temporal mientras no se guarda
     */
    private static function importPreview(mixed $state, bool $replace = false): string
    {
        $file = is_array($state) ? reset($state) : $state;

        if (! $file instanceof TemporaryUploadedFile) {
            return 'Suba el libro para ver qué días entran.';
        }

        try {
            $plan = app(OvertimeWorkbookImporter::class)->plan($file->getRealPath(), Filament::getTenant(), $replace);
        } catch (\Throwable) {
            return 'No se pudo leer el libro. ¿Es el TH-NOM-P-001 en Excel (.xlsx)?';
        }

        $window = $plan['window'] ? 'El libro trae del '.$plan['window'][0]->format('d/m/Y').' al '.$plan['window'][1]->format('d/m/Y').'. ' : '';
        $voids = $replace ? ($plan['toVoid'] === 1 ? 'Se anula 1 marca anterior. ' : "Se anulan {$plan['toVoid']} marcas anteriores. ") : '';

        return $window.$voids.self::importSummary($plan['counts'], $plan['missingWorkers']);
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<string>  $missingWorkers
     */
    private static function importSummary(array $counts, array $missingWorkers, bool $includeImport = true): string
    {
        $parts = [];

        foreach (OvertimeWorkbookImporter::STATUS_LABELS as $status => $label) {
            if (($counts[$status] ?? 0) > 0 && ($includeImport || $status !== 'import')) {
                $parts[] = $label.': '.$counts[$status];
            }
        }

        $text = $parts === [] ? 'El libro no trae días con entrada y salida.' : implode(' · ', $parts).'.';

        if ($missingWorkers !== []) {
            $text .= ' Sin trabajador en la plataforma: '.implode(', ', array_slice($missingWorkers, 0, 6)).(count($missingWorkers) > 6 ? '…' : '').'.';
        }

        return $text;
    }

    /**
     * Borra las horas del período de cada trabajador y avisa cuántos días y marcas quitó.
     *
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function clearHours($employees, string $reason, Action|BulkAction $action): void
    {
        [$from, $to] = $this->window();
        /** @var User $user */
        $user = auth()->user();
        $days = 0;
        $marks = 0;

        try {
            foreach ($employees as $employee) {
                $result = app(AttendanceCorrectionService::class)->clearPeriod($employee, $from, $to, $reason, $user);
                $days += $result['days'];
                $marks += $result['marks'];
            }
        } catch (AttendanceException $e) {
            Notification::make()->title('No se borraron las horas')->body($e->getMessage())->danger()->send();
            $action->halt();

            return;
        }

        Notification::make()
            ->title($days === 1 ? 'Se borró 1 día' : "Se borraron {$days} días")
            ->body(($marks === 1 ? '1 marca quedó anulada' : "{$marks} marcas quedaron anuladas").' en «Marcas de portería». Puede volver a cargarlas a mano o con «Importar libro de horas».')
            ->success()
            ->send();
    }

    /** @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $window */
    private static function clearDescription(array $window): string
    {
        return 'Se quitan los días del '.$window[0]->format('d/m/Y').' al '.$window[1]->format('d/m/Y')
            .', aun confirmados, y sus marcas quedan anuladas: no se borran del todo, se siguen viendo en «Marcas de portería» con el motivo. '
            .'Después puede volver a cargar las horas a mano o con «Importar libro de horas».';
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} la ventana elegida en el filtro */
    public function window(): array
    {
        $value = $this->getTableFilterState('periodo')['value'] ?? null;
        $value = is_string($value) && str_contains($value, '|') ? $value : array_key_first($this->windowOptions());
        [$from, $to] = explode('|', $value);

        return [CarbonImmutable::parse($from), CarbonImmutable::parse($to)];
    }

    /** @return array<string, string> las últimas ventanas de corte, la que corre primero */
    private function windowOptions(): array
    {
        return PayrollRun::recentHoursWindows(
            (int) app(PayrollParameterService::class)->valueOrDefault(PayrollParameter::HoursCutoffDay, CarbonImmutable::today(), Filament::getTenant()?->getKey() ?? ''),
        );
    }

    /** @return array<string, mixed> los totales del trabajador en la ventana elegida */
    private function totalsOf(Employee $employee): array
    {
        [$from, $to] = $this->window();
        $key = $from->toDateString().'|'.$to->toDateString();

        $this->totales[$key] ??= app(OvertimeSummary::class)->forEmployees(
            Employee::query()->active()->pluck('id')->all(),
            $employee->tenant_id,
            $from,
            $to,
        );

        return $this->totales[$key][$employee->getKey()] ?? [];
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
