<?php

namespace App\Enums;

enum ScamTiplineStatus: string
{
    case New = 'new';
    case Investigating = 'investigating';
    case Confirmed = 'confirmed';
    case Dismissed = 'dismissed';
    case Duplicate = 'duplicate';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Investigating => 'Investigating',
            self::Confirmed => 'Confirmed',
            self::Dismissed => 'Dismissed',
            self::Duplicate => 'Duplicate',
        };
    }
}
