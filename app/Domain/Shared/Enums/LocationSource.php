<?php

namespace App\Domain\Shared\Enums;

enum LocationSource: string
{
    case Gps = 'gps';
    case Network = 'network';
    case Unknown = 'unknown';
}
