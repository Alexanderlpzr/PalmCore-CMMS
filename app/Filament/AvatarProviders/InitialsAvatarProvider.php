<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class InitialsAvatarProvider implements AvatarProvider
{
    /** Brand colors for avatar backgrounds — cycles by name hash */
    private const COLORS = [
        '#0F4C5C',
        '#2E8B57',
        '#1a5276',
        '#5b2c6f',
        '#1e8449',
        '#0e6655',
        '#1a5276',
        '#784212',
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
