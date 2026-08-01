<?php

namespace App\Filament\AvatarProviders;

use App\Support\FrondaPalette;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class InitialsAvatarProvider implements AvatarProvider
{
    /**
     * Fondos de avatar, elegidos por hash del nombre. Se decía «brand colors»,
     * pero la lista traía un morado, un marrón y tres azules que no salían de la
     * marca. Ahora alterna tonos de las dos rampas del logotipo: distintos entre
     * sí, y todos lo bastante oscuros para llevar las iniciales en blanco
     * (el más flojo contrasta 5.11:1). Se intercalan verde y petróleo para que
     * dos nombres consecutivos no caigan en tonos casi iguales.
     */
    private const COLORS = [
        FrondaPalette::Brand[600],
        FrondaPalette::Petrol[500],
        FrondaPalette::Brand[800],
        FrondaPalette::Petrol[700],
        FrondaPalette::Brand[700],
        FrondaPalette::Petrol[600],
    ];

    public function get(Model|Authenticatable $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);

        $initials = collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');

        $bg = self::COLORS[abs(crc32($name)) % count(self::COLORS)];

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">
  <rect width="40" height="40" rx="20" fill="{$bg}"/>
  <text x="20" y="27" text-anchor="middle"
        font-family="-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif"
        font-size="15" font-weight="700" fill="white">
    {$initials}
  </text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
