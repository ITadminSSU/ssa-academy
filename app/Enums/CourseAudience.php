<?php

namespace App\Enums;

enum CourseAudience: string
{
    case INTERNAL = 'internal';
    case PUBLIC = 'public';
    case BOTH = 'both';

    public function getLabel(): string
    {
        return match ($this) {
            self::INTERNAL => 'Internal',
            self::PUBLIC => 'Public',
            self::BOTH => 'Both',
        };
    }
}
