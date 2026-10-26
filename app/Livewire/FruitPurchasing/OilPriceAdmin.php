<?php

namespace App\Livewire\FruitPurchasing;

use App\Domain\FruitPurchasing\Services\OilPriceService;
use App\Domain\FruitPurchasing\Support\ColombianNumber;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * /r100/admin: donde un super admin publica el precio de la tonelada de aceite
 * que usa la calculadora pública. La ruta ya exige super admin; las acciones lo
 * vuelven a comprobar porque Livewire las recibe por su propia URL.
 */
class OilPriceAdmin extends Component
{
    public string $newPrice = '';

    public bool $saved = false;

    public function mount(): void
    {
        $this->authorizeSuperAdmin();
    }

    /**
     * @return array{price: float, updatedAt: Carbon|null, updatedBy: string|null}|null
     */
    #[Computed]
    public function current(): ?array
    {
        return app(OilPriceService::class)->current();
    }

    public function save(): void
    {
        $this->authorizeSuperAdmin();
        $this->saved = false;

        $price = ColombianNumber::parse($this->newPrice);

        if ($price === null) {
            $this->addError('newPrice', 'Escriba el precio en pesos, por ejemplo 4.000.000.');

            return;
        }

        if ($price < OilPriceService::MINIMUM_PRICE) {
            $this->addError('newPrice', 'Ese precio es muy bajo para una tonelada. ¿Escribió el precio del kilo? Una tonelada son 1.000 kilos.');

            return;
        }

        if ($price > OilPriceService::MAXIMUM_PRICE) {
            $this->addError('newPrice', 'Ese precio es muy alto para una tonelada. Revise que no tenga un cero de más.');

            return;
        }

        app(OilPriceService::class)->publish($price, auth()->user());
        unset($this->current);

        $this->newPrice = '';
        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.fruit-purchasing.oil-price-admin');
    }

    private function authorizeSuperAdmin(): void
    {
        $user = auth()->user();

        abort_unless($user !== null && $user->is_super_admin && $user->is_active, 403, 'Esta sección es exclusiva para Super Admin.');
    }
}
