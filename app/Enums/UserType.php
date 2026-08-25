<?php

namespace App\Enums;

enum UserType: string
{
    case ADMIN = 'admin';
    case INSTRUCTOR = 'instructor';
    case STUDENT = 'student';
    case SOCIAL_MEDIA = 'social_media';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::INSTRUCTOR => 'instructor',
            self::STUDENT => 'student',
            self::SOCIAL_MEDIA => 'Social Media',
        };
    }
}
