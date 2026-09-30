<?php

namespace App\Enums;

enum CourseStatusType: string
{
    case DRAFT = 'draft';
    case UPCOMING = 'upcoming';
    case COMING_SOON = 'coming_soon';
    case PENDING = 'pending';
    case REJECTED = 'rejected';
    case APPROVED = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::UPCOMING => 'Upcoming',
            self::COMING_SOON => 'Coming Soon',
            self::PENDING => 'Pending',
            self::REJECTED => 'Rejected',
            self::APPROVED => 'Approved',
        };
    }
}
