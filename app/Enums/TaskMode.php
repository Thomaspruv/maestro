<?php

namespace App\Enums;

enum TaskMode: string
{
    case Manual = 'manual';
    case SemiAuto = 'semi_auto';
    case FullAuto = 'full_auto';
}
