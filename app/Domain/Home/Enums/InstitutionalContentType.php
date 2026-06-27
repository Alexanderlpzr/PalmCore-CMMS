<?php

namespace App\Domain\Home\Enums;

enum InstitutionalContentType: string
{
    case Image = 'image';
    case News = 'news';
    case Communication = 'communication';
    case Video = 'video';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Imagen',
            self::News => 'Noticia',
            self::Communication => 'Comunicado',
            self::Video => 'Video',
            self::Pdf => 'PDF',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
