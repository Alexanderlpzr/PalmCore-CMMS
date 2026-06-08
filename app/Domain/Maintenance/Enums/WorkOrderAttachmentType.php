<?php

namespace App\Domain\Maintenance\Enums;

enum WorkOrderAttachmentType: string
{
    case BeforePhoto = 'before_photo';
    case AfterPhoto  = 'after_photo';
    case Evidence    = 'evidence';
    case Report      = 'report';
    case Document    = 'document';

    public function label(): string
    {
        return match ($this) {
            self::BeforePhoto => 'Foto Antes',
            self::AfterPhoto  => 'Foto Después',
            self::Evidence    => 'Evidencia',
            self::Report      => 'Informe',
            self::Document    => 'Documento',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BeforePhoto => 'warning',
            self::AfterPhoto  => 'success',
            self::Evidence    => 'info',
            self::Report      => 'gray',
            self::Document    => 'gray',
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
