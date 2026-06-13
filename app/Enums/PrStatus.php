<?php

namespace App\Enums;

enum PrStatus: string
{
    case None = 'none';
    case Open = 'open';
    case Draft = 'draft';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Merged = 'merged';
    case Closed = 'closed';
}
