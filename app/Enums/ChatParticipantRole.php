<?php

namespace App\Enums;

enum ChatParticipantRole: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Admin = 'admin';
}
