<?php

namespace App\Enums;

enum LearnerUserType: string
{
    case EMPLOYEE = 'employee';
    case EXTERNAL = 'external';

    public function getLabel(): string
    {
        return match ($this) {
            self::EMPLOYEE => 'Employee',
            self::EXTERNAL => 'External',
        };
    }
}
