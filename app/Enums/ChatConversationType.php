<?php

namespace App\Enums;

enum ChatConversationType: string
{
    case Direct = 'direct';
    case Group = 'group';
    case Academy = 'academy';
}
