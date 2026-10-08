<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Resources\AttendanceDays\AttendanceDayActions;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollRun;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Los días ya firmados en «Horas por confirmar», para corregirlos cuando llega una novedad
 * tarde: ajustar sus horas o anularlo, con motivo. El día sigue confirmado y queda quién,
 * cuándo y por qué lo cambió. Lo que ya se pagó en una nómina cerrada no se deja tocar.
 */
class HorasConfirmadas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano';

    protected static ?string $navigationLabel = 'Horas confirmadas';

    protected static ?string $title = 'Horas confirmadas';

    /** Entre «Horas por confirmar» y «Horas extras»: el orden en que se trabajan. */
    protected static ?int $navigationSort = 21;

    protected static ?string $slug = 'horas-confirmadas';

    protected string $view = 'filament.pages.horas-confirmadas';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', AttendanceDay::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AttendanceDay::query()->confirmed()->with(['employee', 'confirmedBy:id,name', 'adjustedBy:id,name']))
            ->defaultSort('work_date', 'desc')
            ->emptyStateHeading('Sin días confirmados en el periodo')
            ->columns([
                TextColumn::make('work_date')
                    ->label('Fecha')
                    ->date('D d/m/Y')
                    ->sortable(),
                TextColumn::make('employee.first_name')
                    ->label('Trabajador')
                    ->getStateUsing(fn (AttendanceDay $record): string => $record->employee?->fullName() ?? '—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('employee', fn (Builder $e) => $e
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%"))),
                TextColumn::make('worked_hours')
                    ->label('Trabajadas')
                    ->numeric(2)
                    ->alignEnd()
                    ->description(fn (AttendanceDay $record): ?string => $record->isManuallyAdjusted() ? 'Ajustado a mano' : null)
                    ->tooltip(fn (AttendanceDay $record): ?string => $record->isManuallyAdjusted()
                        ? 'Ajustado por '.($record->adjustedBy?->name ?? '—').' el '.$record->adjusted_at?->format('d/m/Y').': '.$record->adjustment_reason
                        : null),
                TextColumn::make('night_surcharge_hours')->label('Rec. nocturno')->numeric(2)->alignEnd()->toggleable(),
                TextColumn::make('sunday_surcharge_hours')->label('Rec. dominical')->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('night_sunday_surcharge_hours')->label('Rec. noct. dom.')->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_day_hours')->label('Extra diurna')->numeric(2)->alignEnd()->toggleable(),
                TextColumn::make('overtime_night_hours')->label('Extra nocturna')->numeric(2)->alignEnd()->toggleable(),
                TextColumn::make('overtime_sunday_day_hours')->label('Extra dom. diurna')->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_sunday_night_hours')->label('Extra dom. noct.')->numeric(2)->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('confirmedBy.name')
                    ->label('Firmado por')
                    ->description(fn (AttendanceDay $record): ?string => $record->confirmed_at?->format('d/m/Y'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options(fn (): array => $this->windowOptions())
                    ->default(fn (): ?string => array_key_first($this->windowOptions()))
                    ->query(function (Builder $query, array $data): Builder {
                        if (! is_string($data['value'] ?? null) || ! str_contains($data['value'], '|')) {
                            return $query;
                        }

                        [$from, $to] = explode('|', $data['value']);

                        return $query->whereBetween('work_date', [$from, $to]);
                    }),
                SelectFilter::make('employee_id')
                    ->label('Trabajador')
                    ->options(fn (): array => Employee::query()->active()->orderBy('first_name')->get()
                        ->mapWithKeys(fn (Employee $e): array => [$e->id => $e->fullName()])->all())
                    ->searchable(),
            ])
            ->recordActions([
                AttendanceDayActions::adjustHours(),
                AttendanceDayActions::voidDay(),
            ]);
    }

    /** @return array<string, string> */
    private function windowOptions(): array
    {
        return PayrollRun::recentHoursWindows(
            (int) app(PayrollParameterService::class)->valueOrDefault(PayrollParameter::HoursCutoffDay, CarbonImmutable::today(), Filament::getTenant()?->getKey() ?? ''),
        );
    }
}
