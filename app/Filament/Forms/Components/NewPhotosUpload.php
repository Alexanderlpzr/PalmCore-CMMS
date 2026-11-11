<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload;

/**
 * Fotos que solo se suben nuevas, en una ventana cuyo registro no las guarda: las de lo
 * que hizo un área en su parte del hallazgo, que viven en la parte y no en el hallazgo.
 *
 * Sigue cerrando la puerta a rutas inventadas: como no hay fotos guardadas que conservar,
 * solo pasan las que se suben ahí mismo. FileUpload las buscaría en el registro de la
 * página y, al no tener ese campo, fallaría.
 */
class NewPhotosUpload extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->image()->multiple()->preventFilePathTampering();
    }

    /** @return array<string> */
    public function getOriginalFilePaths(): array
    {
        return [];
    }
}
