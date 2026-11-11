<?php

namespace App\Filament\Resources\Safety;

use App\Domain\Safety\Enums\FindingOrigin;
use App\Domain\Safety\Enums\FindingPriority;
use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Enums\ResponsibleArea;
use App\Filament\Resources\Safety\Pages\CreateSstFinding;
use App\Filament\Resources\Safety\Pages\EditSstFinding;
use App\Filament\Resources\Safety\Pages\ListSstFindings;
use App\Filament\Resources\Safety\Pages\ViewSstFinding;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\SstFinding;
use App\Models\SstFindingTask;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Los hallazgos de SST: el cuadro que SST llevaba en Excel, ahora en Fronda. SST los
 * reporta con su foto, su ubicación, su prioridad —que fija el plazo— y las áreas que
 * los corrigen; cada área marca su parte y SST verifica el arreglo.
 */
class SstFindingResource extends Resource
{
    protected static ?string $model = SstFinding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $modelLabel = 'Hallazgo';

    protected static ?string $pluralModelLabel = 'Hallazgos SST y ambientales';

    protected static ?string $navigationLabel = 'Hallazgos';

    protected static string|UnitEnum|null $navigationGroup = 'Seguridad y Salud en el Trabajo';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'hallazgos-sst';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof SstFinding ? "Hallazgo {$record->code()}" : 'Hallazgo';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('El hallazgo')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('origin')
                        ->label('Origen')
                        ->options(FindingOrigin::options())
                        ->default(fn (): string => (FindingOrigin::forUser(self::user()) ?? FindingOrigin::Sst)->value)
                        ->selectablePlaceholder(false)
                        ->helperText('SST o Gestión Ambiental: quién lo levantó, para la trazabilidad.')
                        ->required(),
                    DatePicker::make('reported_on')
                        ->label('Fecha de reporte')
                        ->default(fn (): string => SstFinding::today()->toDateString())
                        ->maxDate(fn (): string => SstFinding::today()->toDateString())
                        ->live()
                        ->required(),
                    Select::make('priority')
                        ->label('Prioridad')
                        ->options(FindingPriority::options())
                        ->default(FindingPriority::Media->value)
                        ->live()
                        ->helperText(fn (Get $get): ?string => self::dueText($get('reported_on'), $get('priority')))
                        ->required(),
                    Textarea::make('description')
                        ->label('Descripción del hallazgo')
                        ->rows(3)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->columnSpanFull(),
                    FileUpload::make('photo_before_path')
                        ->label('Foto del hallazgo')
                        ->image()
                        ->disk(persistent_disk())
                        ->directory(fn (): string => 'sst-findings/'.filament()->getTenant()?->getKey())
                        ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
                        ->preventFilePathTampering()
                        ->maxSize(10240)
                        ->columnSpanFull(),
                ]),

            Section::make('Ubicación')
                ->columns(['default' => 1, 'sm' => 3])
                ->schema([
                    Select::make('area_id')
                        ->label('Sección')
                        ->options(fn (): array => Area::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('equipment_id', null))
                        ->required(fn (Get $get): bool => in_array(ResponsibleArea::Mantenimiento->value, (array) $get('areas'), true) && blank($get('equipment_id')))
                        ->helperText('Si es de mantenimiento y no es de un equipo, va a «Infraestructura» de la sección.'),
                    Select::make('equipment_id')
                        ->label('Equipo (opcional)')
                        ->options(fn (Get $get): array => Equipment::query()
                            ->where('is_active', true)
                            ->when($get('area_id'), fn (Builder $query, string $areaId) => $query->where('area_id', $areaId))
                            ->orderBy('name')
                            ->limit(500)
                            ->get()
                            ->mapWithKeys(fn (Equipment $equipment): array => [$equipment->id => "{$equipment->code} · {$equipment->name}"])
                            ->all())
                        ->searchable(),
                    TextInput::make('location_detail')
                        ->label('Lugar exacto')
                        ->placeholder('Ej.: escalera del segundo piso')
                        ->maxLength(255),
                ]),

            Section::make('Quién lo corrige')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    CheckboxList::make('areas')
                        ->label('Áreas responsables')
                        ->options(ResponsibleArea::options())
                        ->columns(2)
                        ->live()
                        ->required()
                        ->visibleOn('create')
                        ->helperText('Mantenimiento lo recibe como reporte de novedad y lo vuelve OT; al cerrar la OT su parte queda hecha.'),
                    Textarea::make('action_plan')
                        ->label('Plan de acción')
                        ->rows(3),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'lg' => 4])
                ->schema([
                    TextEntry::make('number')->label('Hallazgo')->formatStateUsing(fn (SstFinding $record): string => $record->code()),
                    TextEntry::make('status')->label('Estado')->badge()
                        ->formatStateUsing(fn (FindingStatus $state): string => $state->label())
                        ->color(fn (FindingStatus $state): string => $state->color()),
                    TextEntry::make('priority')->label('Prioridad')->badge()
                        ->formatStateUsing(fn (FindingPriority $state): string => $state->label())
                        ->color(fn (FindingPriority $state): string => $state->color()),
                    TextEntry::make('due_on')->label('Vence')->date('d/m/Y')
                        ->color(fn (SstFinding $record): ?string => $record->isOverdue() ? 'danger' : null)
                        ->helperText(fn (SstFinding $record): ?string => $record->isOverdue() ? 'Vencido' : null),
                    TextEntry::make('reported_on')->label('Fecha de reporte')->date('d/m/Y'),
                    TextEntry::make('origin')->label('Origen')->badge()
                        ->formatStateUsing(fn (FindingOrigin $state): string => $state->label())
                        ->color(fn (FindingOrigin $state): string => $state->color())
                        ->icon(fn (FindingOrigin $state): string => $state->icon())
                        ->helperText(fn (SstFinding $record): ?string => $record->source_item !== null && $record->source_item !== $record->number ? "ITM {$record->source_item} de su cuadro de Excel" : null),
                    TextEntry::make('createdBy.name')->label('Lo reportó')->placeholder('—'),
                    TextEntry::make('executed_on')->label('Fecha de corrección')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('verified_at')->label('Verificado')
                        ->formatStateUsing(fn (SstFinding $record): string => $record->verified_at->format('d/m/Y').($record->verifiedBy ? ' · '.$record->verifiedBy->name : ''))
                        ->placeholder('—'),
                    TextEntry::make('location')->label('Ubicación')->state(fn (SstFinding $record): string => $record->locationLabel())->columnSpan(2),
                    TextEntry::make('description')->label('Descripción')->placeholder('Sin descripción: ver la foto')->columnSpanFull(),
                    TextEntry::make('action_plan')->label('Plan de acción')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('verification_notes')->label('Observación de la verificación')->placeholder('—')->columnSpanFull()
                        ->visible(fn (SstFinding $record): bool => filled($record->verification_notes)),
                ]),

            Section::make('Fotos')
                ->columns(2)
                ->schema([
                    ImageEntry::make('photo_before_path')->label('Antes')->disk(persistent_disk())->imageHeight(220)->placeholder('Sin foto'),
                    ImageEntry::make('photo_after_path')->label('Después')->disk(persistent_disk())->imageHeight(220)->placeholder('Todavía sin foto'),
                ]),

            Section::make('Áreas responsables')
                ->schema([
                    RepeatableEntry::make('tasks')
                        ->hiddenLabel()
                        ->columns(['default' => 2, 'lg' => 5])
                        ->schema([
                            TextEntry::make('area')->label('Área')->formatStateUsing(fn (ResponsibleArea $state): string => $state->label())->weight('bold'),
                            TextEntry::make('status')->label('Estado')->badge()
                                ->formatStateUsing(fn (FindingStatus $state): string => $state->label())
                                ->color(fn (FindingStatus $state): string => $state->color()),
                            TextEntry::make('executed_on')->label('Corregido')->date('d/m/Y')->placeholder('—'),
                            TextEntry::make('executedBy.name')->label('Quién')->placeholder('—'),
                            TextEntry::make('issue_report')->label('En mantenimiento')
                                ->state(fn (SstFindingTask $record): ?string => self::maintenanceText($record))
                                ->placeholder('—'),
                            TextEntry::make('notes')->label('Qué se hizo')->placeholder('—')->columnSpanFull()
                                ->visible(fn (SstFindingTask $record): bool => filled($record->notes)),
                            ImageEntry::make('evidence_paths')->label('Fotos de lo que se hizo')
                                ->disk(persistent_disk())
                                ->imageHeight(140)
                                ->columnSpanFull()
                                ->visible(fn (SstFindingTask $record): bool => filled($record->evidence_paths)),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['area', 'equipment', 'tasks']))
            ->columns([
                TextColumn::make('number')->label('N.º')
                    ->formatStateUsing(fn (SstFinding $record): string => $record->code())
                    ->sortable(),
                // Sin píldora: la tabla ya lleva dos (prioridad y estado).
                TextColumn::make('origin')->label('Origen')
                    ->formatStateUsing(fn (FindingOrigin $state): string => $state->label())
                    ->color(fn (FindingOrigin $state): string => $state->color())
                    ->icon(fn (FindingOrigin $state): string => $state->icon())
                    ->sortable(),
                ImageColumn::make('photo_before_path')->label('Foto')->disk(persistent_disk())->height(44)->square(),
                TextColumn::make('description')->label('Hallazgo')
                    ->placeholder('Ver foto')
                    ->limit(60)
                    ->tooltip(fn (SstFinding $record): ?string => $record->description)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('location')->label('Ubicación')
                    ->state(fn (SstFinding $record): string => $record->locationLabel())
                    ->limit(40)
                    ->tooltip(fn (SstFinding $record): string => $record->locationLabel()),
                TextColumn::make('priority')->label('Prioridad')->badge()
                    ->formatStateUsing(fn (FindingPriority $state): string => $state->label())
                    ->color(fn (FindingPriority $state): string => $state->color()),
                TextColumn::make('responsables')->label('Responsables')
                    ->state(fn (SstFinding $record): string => $record->tasks->map(fn (SstFindingTask $task): string => $task->area->label().($task->status === FindingStatus::Ejecutado ? ' ✓' : ''))->implode(', ')),
                TextColumn::make('reported_on')->label('Reporte')->date('d/m/Y')->sortable(),
                TextColumn::make('due_on')->label('Vence')->date('d/m/Y')->sortable()
                    ->color(fn (SstFinding $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->weight(fn (SstFinding $record): ?string => $record->isOverdue() ? 'bold' : null),
                TextColumn::make('executed_on')->label('Corrección')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (FindingStatus $state): string => $state->label())
                    ->color(fn (FindingStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('origin')->label('Origen')->options(FindingOrigin::options()),
                SelectFilter::make('status')->label('Estado')->options(FindingStatus::options()),
                SelectFilter::make('priority')->label('Prioridad')->options(collect(FindingPriority::cases())->mapWithKeys(fn (FindingPriority $priority): array => [$priority->value => $priority->label()])->all()),
                SelectFilter::make('area_id')->label('Sección')->relationship('area', 'name'),
                SelectFilter::make('responsible')->label('Área responsable')
                    ->options(ResponsibleArea::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->whereHas('tasks', fn (Builder $tasks) => $tasks->where('area', $data['value']))
                        : $query),
                Filter::make('overdue')->label('Solo vencidos')->query(fn (Builder $query): Builder => $query->overdue()),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (SstFinding $record): string => self::getUrl('view', ['record' => $record]))
            ->defaultSort('number', 'desc')
            ->emptyStateHeading('No hay hallazgos aquí')
            ->emptyStateDescription('Reporte uno con su foto, su ubicación y las áreas que lo corrigen.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSstFindings::route('/'),
            'create' => CreateSstFinding::route('/create'),
            'view' => ViewSstFinding::route('/{record}'),
            'edit' => EditSstFinding::route('/{record}/edit'),
        ];
    }

    /** «Vence el 13/11/2026 (8 días)». */
    public static function dueText(mixed $reportedOn, mixed $priority): ?string
    {
        if (blank($reportedOn) || blank($priority) || ! ($priority = FindingPriority::tryFrom((string) $priority))) {
            return null;
        }

        return 'Vence el '.CarbonImmutable::parse($reportedOn)->addDays($priority->days())->format('d/m/Y')." ({$priority->days()} días).";
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function maintenanceText(SstFindingTask $task): ?string
    {
        if ($task->area !== ResponsibleArea::Mantenimiento || $task->issueReport === null) {
            return null;
        }

        $workOrder = $task->issueReport->workOrder;

        return $workOrder
            ? "OT {$workOrder->work_order_number} · {$workOrder->status->label()}"
            : 'Reporte de novedad: '.$task->issueReport->status->label();
    }
}
