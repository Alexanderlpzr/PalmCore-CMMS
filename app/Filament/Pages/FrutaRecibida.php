<?php

namespace App\Filament\Pages;

use App\Domain\Commercial\Services\AnnualPlanService;
use App\Domain\Commercial\Services\CommercialPlanReport;
use App\Models\FruitReception;
use App\Models\FruitSupplier;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * La fruta que entregó cada proveedor, mes a mes, según la báscula: la tabla «Entrega real
 * de fruta por proveedor» del Excel de la Directora Administrativa. Debajo, la fruta que
 * procesó la planta según su calendario de producción, para que las dos cuadren.
 */
class FrutaRecibida extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Fruta recibida';

    protected static ?string $title = 'Fruta recibida';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'fruta-recibida';

    protected string $view = 'filament.pages.fruta-recibida';

    #[Url(as: 'anio')]
    public ?int $year = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', FruitReception::class) ?? false;
    }

    public function mount(): void
    {
        $this->year ??= (int) now()->format('Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('proveedor')
                ->label('Nuevo proveedor')
                ->icon(Heroicon::OutlinedPlus)
                ->color('gray')
                ->visible(fn (): bool => $this->canManage())
                ->schema([
                    TextInput::make('name')->label('Nombre del proveedor')->required()->maxLength(150),
                ])
                ->action(function (array $data): void {
                    if (FruitSupplier::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
                        Notification::make()->title('Ese proveedor ya existe')->warning()->send();

                        return;
                    }

                    FruitSupplier::query()->create(['name' => trim($data['name']), 'is_active' => true]);
                    Notification::make()->title('Proveedor creado')->success()->send();
                }),
        ];
    }

    public function guardar(string $supplierId, int $month, ?string $value): void
    {
        if (! $this->canManage()) {
            return;
        }

        try {
            $user = auth()->user();
            app(AnnualPlanService::class)->setReception((string) filament()->getTenant()->getKey(), $supplierId, (int) $this->year, $month, $value, $user instanceof User ? $user : null);
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /** @return array{real: array<string, array<int, float>>, plan: array<string, array<int, float>>, processed: array<int, float>} */
    public function data(): array
    {
        $report = app(CommercialPlanReport::class);
        $tenantId = (string) filament()->getTenant()->getKey();
        $plan = app(AnnualPlanService::class)->current($tenantId, (int) $this->year);

        return [
            'real' => $report->fruitReal($tenantId, (int) $this->year),
            'plan' => $report->fruitPlan($plan),
            'processed' => $report->plantProcessed($tenantId, (int) $this->year),
        ];
    }

    /** @return Collection<int, FruitSupplier> */
    public function suppliers(array $withValues): Collection
    {
        return FruitSupplier::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', array_keys($withValues)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function canManage(): bool
    {
        return auth()->user()?->can('create', FruitReception::class) ?? false;
    }
}
