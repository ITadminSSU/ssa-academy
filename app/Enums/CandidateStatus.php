<?php

namespace App\Enums;

enum CandidateStatus: string
{
    case NEW = 'new';
    case IN_REVIEW = 'in_review';
    case SHORTLISTED = 'shortlisted';
    case HIRED = 'hired';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::IN_REVIEW => 'In Review',
            self::SHORTLISTED => 'Shortlisted',
            self::HIRED => 'Hired',
            self::REJECTED => 'Rejected',
        };
    }
}
