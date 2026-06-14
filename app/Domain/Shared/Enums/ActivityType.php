<?php

namespace App\Domain\Shared\Enums;

enum ActivityType: string
{
    case TimeLog = 'time_log';
    case Comment = 'comment';
    case Photo = 'photo';
    case Signature = 'signature';
    case StatusChange = 'status_change';
}
