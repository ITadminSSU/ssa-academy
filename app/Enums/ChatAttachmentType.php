<?php

namespace App\Enums;

enum ChatAttachmentType: string
{
    case Image = 'image';
    case Video = 'video';
    case Pdf = 'pdf';
}
