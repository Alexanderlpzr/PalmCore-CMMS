<?php

namespace App\Livewire\FruitPurchasing;

use App\Models\R100Lead;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Casilla al pie de la calculadora R-100 para que los interesados dejen su
 * correo. Solo se guarda el correo y la fecha en que autorizaron guardarlo
 * (Ley 1581 de 2012). La lista la ve un super admin en /r100/admin.
 */
class InterestedSignup extends Component
{
    public string $email = '';

    public bool $consent = false;

    /** Campo trampa: las personas no lo ven; los robots que llenan todo, sí. */
    public string $website = '';

    public ?string $savedEmail = null;

    public function save(): void
    {
        $key = 'r100-lead:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->addError('email', 'Ya recibimos varios correos desde aquí. Intente de nuevo en unos minutos.');

            return;
        }

        $this->email = mb_strtolower(trim($this->email));

        $this->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'consent' => ['accepted'],
        ], [
            'email.required' => 'Escriba su correo.',
            'email.email' => 'Ese correo no parece válido. Revíselo, por ejemplo: nombre@gmail.com',
            'email.max' => 'Ese correo es demasiado largo.',
            'consent.accepted' => 'Para guardar su correo necesitamos que marque la autorización.',
        ]);

        RateLimiter::hit($key, decaySeconds: 600);

        if ($this->website === '') {
            R100Lead::query()->updateOrCreate(['email' => $this->email], ['consented_at' => now()]);
        }

        $this->savedEmail = $this->email;
        $this->reset(['email', 'consent', 'website']);
    }

    public function render(): View
    {
        return view('livewire.fruit-purchasing.interested-signup');
    }
}
