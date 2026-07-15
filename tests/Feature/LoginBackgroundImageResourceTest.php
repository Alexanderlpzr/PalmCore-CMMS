<?php

use App\Filament\Platform\Resources\LoginBackgroundImages\LoginBackgroundImageResource;
use App\Filament\Platform\Resources\LoginBackgroundImages\Pages\CreateLoginBackgroundImage;
use App\Filament\Platform\Resources\LoginBackgroundImages\Pages\ListLoginBackgroundImages;
use App\Models\LoginBackgroundImage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('platform'));
});

// ── Autorización ──────────────────────────────────────────────────────────────

it('el superadministrador puede ver el listado', function (): void {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));

    Livewire::test(ListLoginBackgroundImages::class)->assertOk();
});

it('un usuario normal no puede ver el recurso', function (): void {
    $this->actingAs(User::factory()->create(['is_super_admin' => false, 'is_active' => true]));

    expect(LoginBackgroundImageResource::canViewAny())->toBeFalse();
});

// ── CRUD ──────────────────────────────────────────────────────────────────────

it('el superadministrador puede subir una imagen para el carrusel de login', function (): void {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));

    Livewire::test(CreateLoginBackgroundImage::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('planta.jpg', 900, 1200),
            'caption' => 'Planta El Pajuil',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LoginBackgroundImage::where('caption', 'Planta El Pajuil')->exists())->toBeTrue();
});

// ── Visibilidad ───────────────────────────────────────────────────────────────

it('visible() solo incluye imagenes activas, ordenadas por sort_order', function (): void {
    $second = LoginBackgroundImage::factory()->create(['sort_order' => 2]);
    $first = LoginBackgroundImage::factory()->create(['sort_order' => 1]);
    LoginBackgroundImage::factory()->inactive()->create(['sort_order' => 0]);

    $visible = LoginBackgroundImage::visible()->get();

    expect($visible->pluck('id')->all())->toBe([$first->id, $second->id]);
});
