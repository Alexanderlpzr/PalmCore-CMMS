<?php

namespace App\Filament\Resources\Downtime\Tables;

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageConfirmationStatus;
use App\Domain\Assets\Services\DowntimeService;
use App\Models\EquipmentDowntimeEvent;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DowntimeEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('equipment.code')
                    ->label('Equipo')
                    ->searchable()
                    ->placeholder('Paro de planta'),
                TextColumn::make('stoppage_category')
                    ->label('Tipo I')
                    ->badge()
                    ->formatStateUsing(fn (?StoppageCategory $state): string => $state?->label() ?? '—')
                    ->color(fn (?StoppageCategory $state): string => $state?->color() ?? 'gray'),
                TextColumn::make('stoppage_cause')
                    ->label('Tipo II')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('duration_minutes')
                    ->label('Duración')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? 'En curso'
                        : sprintf('%dh %02dmin', intdiv($state, 60), $state % 60))
                    ->sortable(),
                IconColumn::make('affects_production')
                    ->label('Resta horas')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('confirmation_status')
                    ->label('Firma de producción')
                    ->badge()
                    ->formatStateUsing(fn (?StoppageConfirmationStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?StoppageConfirmationStatus $state): string => $state?->color() ?? 'gray'),
                TextColumn::make('work_order_number')
                    ->label('OT')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('stoppage_category')
                    ->label('Tipo I')
                    ->options(StoppageCategory::options()),
                SelectFilter::make('confirmation_status')
                    ->label('Firma de producción')
                    ->options(StoppageConfirmationStatus::options()),
                TernaryFilter::make('ended_at')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Cerrados')
                    ->falseLabel('En curso')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('ended_at'),
                        false: fn (Builder $query) => $query->whereNull('ended_at'),
                    ),
                // M1 — los paros que nacieron de una OT y nadie diagnosticó. En vez de
                // rellenarlos con una categoría inventada, se hacen visibles para que
                // alguien que sabe qué se rompió los clasifique.
                Filter::make('unclassified')
                    ->label('Sin clasificar')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('was_planned', false)
                        ->where(fn (Builder $q) => $q
                            ->whereNull('stoppage_category')
                            ->orWhere('stoppage_category', StoppageCategory::Other->value))),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::endAction(),
                    self::classifyAction(),
                    self::confirmAction(),
                    self::disputeAction(),
                ]),
            ])
            ->defaultSort('started_at', 'desc');
    }

    /** Cerrar el paro: la línea volvió a arrancar. */
    private static function endAction(): Action
    {
        return Action::make('end')
            ->label('Cerrar paro')
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(fn (EquipmentDowntimeEvent $record): bool => $record->isOngoing()
                && auth()->user()->can('update', $record))
            ->schema([
                DateTimePicker::make('ended_at')
                    ->label('Fin del paro')
                    ->seconds(false)
                    ->default(now())
                    ->required(),
                Textarea::make('notes')->label('Notas')->rows(2),
            ])
            ->action(fn (EquipmentDowntimeEvent $record, array $data) => self::run(
                fn () => app(DowntimeService::class)->end($record, $data['ended_at'], $data['notes'] ?? null),
                'Paro cerrado',
            ));
    }

    /** A4 — el Tipo I que solo se sabe al destapar la máquina. */
    private static function classifyAction(): Action
    {
        return Action::make('classify')
            ->label('Clasificar Tipo I')
            ->icon('heroicon-o-tag')
            ->visible(fn (EquipmentDowntimeEvent $record): bool => ! $record->was_planned
                && auth()->user()->can('update', $record))
            ->schema([
                Select::make('stoppage_category')
                    ->label('Tipo I diagnosticado')
                    // «Programado» no se diagnostica: lo decide el origen del paro.
                    ->options(collect(StoppageCategory::options())
                        ->except(StoppageCategory::Planned->value)
                        ->all())
                    ->required()
                    ->native(false),
                TextInput::make('stoppage_cause')
                    ->label('Tipo II — causa específica')
                    ->maxLength(255),
            ])
            ->action(fn (EquipmentDowntimeEvent $record, array $data) => self::run(
                fn () => app(DowntimeService::class)->reclassify(
                    $record,
                    StoppageCategory::from($data['stoppage_category']),
                    $data['stoppage_cause'] ?? null,
                ),
                'Tipo I actualizado',
            ));
    }

    /** A5 — producción firma las horas que se le restan a la planta. */
    private static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label('Confirmar horas')
            ->icon('heroicon-o-hand-thumb-up')
            ->color('success')
            ->visible(fn (EquipmentDowntimeEvent $record): bool => self::isSignable($record))
            ->schema([
                Textarea::make('notes')->label('Notas (opcional)')->rows(2),
            ])
            ->action(fn (EquipmentDowntimeEvent $record, array $data) => self::run(
                fn () => app(DowntimeService::class)->confirm($record, auth()->user(), $data['notes'] ?? null),
                'Horas confirmadas por producción',
            ));
    }

    /** Producción no está de acuerdo. El paro no desaparece: queda en disputa. */
    private static function disputeAction(): Action
    {
        return Action::make('dispute')
            ->label('No estoy de acuerdo')
            ->icon('heroicon-o-hand-thumb-down')
            ->color('danger')
            ->visible(fn (EquipmentDowntimeEvent $record): bool => self::isSignable($record))
            ->schema([
                Textarea::make('reason')
                    ->label('¿Por qué?')
                    ->helperText('El paro sigue contando en los indicadores. La objeción queda registrada al lado.')
                    ->required()
                    ->minLength(5)
                    ->rows(3),
            ])
            ->action(fn (EquipmentDowntimeEvent $record, array $data) => self::run(
                fn () => app(DowntimeService::class)->dispute($record, auth()->user(), $data['reason']),
                'Disputa registrada',
            ));
    }

    private static function isSignable(EquipmentDowntimeEvent $record): bool
    {
        return $record->requiresProductionConfirmation()
            && ! $record->isSignedByProduction()
            && auth()->user()->can('confirm', $record);
    }

    /**
     * Las reglas viven en el servicio y hablan español. Aquí solo se traduce la
     * negativa a una notificación en vez de a una pantalla de error.
     */
    private static function run(callable $operation, string $successMessage): void
    {
        try {
            DB::transaction($operation);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($successMessage)->success()->send();
    }
}
