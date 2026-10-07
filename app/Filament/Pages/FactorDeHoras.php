<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Services\HoursFactorReport;
use App\Models\PayrollRun;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * El indicador «factor de horas» de la extractora (TH-INF-F-002), hecho de la nómina
 * liquidada en vez de tecleado: por grupo, lo ordinario, los recargos y las horas extras
 * —incluidas las que se pagaron como bonificación— y la comparación con el mes anterior.
 *
 * Lo ve quien ve la nómina: talento humano.
 */
class FactorDeHoras extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano';

    protected static ?string $navigationLabel = 'Factor de horas';

    protected static ?string $title = 'Factor de horas';

    /** Justo después de «Nóminas»: es su lectura. */
    protected static ?int $navigationSort = 26;

    protected static ?string $slug = 'factor-de-horas';

    protected string $view = 'filament.pages.factor-de-horas';

    /** La nómina que se mira, en la dirección para poder compartirla. */
    #[Url(as: 'nomina')]
    public ?string $runId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', PayrollRun::class) ?? false;
    }

    public function mount(): void
    {
        if ($this->runId === null || ! $this->runs()->has($this->runId)) {
            $this->runId = $this->runs()->keys()->first();
        }
    }

    /**
     * Las nóminas ya liquidadas, de la más reciente a la más antigua.
     *
     * @return Collection<string, string>
     */
    public function runs(): Collection
    {
        return PayrollRun::query()
            ->whereNotNull('calculated_at')
            ->orderByDesc('period_start')
            ->get()
            ->mapWithKeys(fn (PayrollRun $run): array => [$run->getKey() => $run->name]);
    }

    /**
     * El informe de la nómina elegida y el de la anterior, para comparar.
     *
     * @return array{run: PayrollRun, current: array<string, mixed>, previousRun: ?PayrollRun, previous: ?array<string, mixed>}|null
     */
    public function report(): ?array
    {
        $run = $this->runId ? PayrollRun::query()->find($this->runId) : null;

        if (! $run) {
            return null;
        }

        $service = app(HoursFactorReport::class);
        $previousRun = $service->previousRun($run);

        return [
            'run' => $run,
            'current' => $service->forRun($run),
            'previousRun' => $previousRun,
            'previous' => $previousRun ? $service->forRun($previousRun) : null,
        ];
    }
}
