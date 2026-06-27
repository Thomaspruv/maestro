<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Backlog = 'backlog';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case WaitingHermes = 'waiting_hermes';
    case Done = 'done';
    case Failed = 'failed';
}
