<?php

namespace App\Filament\Pages;

use App\Domain\Maintenance\Enums\WorkedHoursPeriodType;
use App\Domain\Maintenance\Services\EquipmentWorkedHoursService;
use App\Models\Equipment;
use App\Models\EquipmentWorkedHours;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Horas trabajadas por equipo, por fuera del dial de horómetro (MeterReadingResource).
 *
 * El desplegable de arriba decide el modo: Diario/Semanal son captura — un
 * formulario por equipo con fecha y horas — mientras que Mensual/Anual no
 * capturan nada, son la suma de esos dos para todos los equipos en el rango
 * elegido (EquipmentWorkedHoursService::summary()).
 */
class WorkedHoursLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Horas trabajadas';

    protected static ?string $title = 'Registro de horas trabajadas';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 7;

    protected static bool $isScopedToTenant = true;

    protected string $view = 'filament.pages.worked-hours-log';

    public string $viewMode = 'diario';

    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_super_admin || $user?->can('equipment-meter-readings.view'));
    }

    public function updatedViewMode(): void
    {
        $this->resetTable();
    }

    public function isPeriodEntryMode(): bool
    {
        return in_array($this->viewMode, ['diario', 'semanal'], strict: true);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('registerWorkedHours')
                ->label('Registrar horas')
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => $this->isPeriodEntryMode() && (bool) auth()->user()?->can('create', EquipmentWorkedHours::class))
                ->modalHeading(fn (): string => $this->viewMode === 'semanal' ? 'Registrar horas — semanal' : 'Registrar horas — diario')
                ->schema([
                    Select::make('equipment_id')
                        ->label('Equipo')
                        ->options(fn (): array => Equipment::orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Equipment $equipment): array => [
                                $equipment->id => "{$equipment->code} — {$equipment->name}",
                            ])
                            ->all())
                        ->searchable()
                        ->native(false)
                        ->required(),
                    DatePicker::make('log_date')
                        ->label('Fecha')
                        ->native(false)
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    TextInput::make('hours')
                        ->label('Horas trabajadas')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $equipment = Equipment::findOrFail($data['equipment_id']);

                    app(EquipmentWorkedHoursService::class)->record(
                        equipment: $equipment,
                        periodType: WorkedHoursPeriodType::from($this->viewMode),
                        hours: (float) $data['hours'],
                        logDate: Carbon::parse($data['log_date']),
                        recordedBy: auth()->user(),
                    );

                    Notification::make()
                        ->title('Horas registradas')
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EquipmentWorkedHours::query()->when(
                    $this->isPeriodEntryMode(),
                    fn (Builder $query): Builder => $query->where('period_type', $this->viewMode),
                    fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                )
            )
            ->groups([
                Group::make('equipment.code')
                    ->label('Equipo')
                    ->collapsible(),
            ])
            ->defaultGroup('equipment.code')
            ->columns([
                TextColumn::make('log_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('equipment.code')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('hours')
                    ->label('Horas')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('recordedBy.name')
                    ->label('Registró')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('log_date', 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        if ($this->isPeriodEntryMode()) {
            return [];
        }

        [$from, $to] = $this->viewMode === 'anual'
            ? [Carbon::create($this->year, 1, 1)->startOfYear(), Carbon::create($this->year, 1, 1)->endOfYear()]
            : [Carbon::create($this->year, $this->month, 1)->startOfMonth(), Carbon::create($this->year, $this->month, 1)->endOfMonth()];

        return [
            'summary' => app(EquipmentWorkedHoursService::class)->summary(Filament::getTenant()->id, $from, $to),
        ];
    }
}
