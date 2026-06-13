<?php

namespace App\Enums;

enum PipelineHealthState: string
{
    case NotStarted = 'not_started';
    case Queued = 'queued';
    case Running = 'running';
    case WaitingGate = 'waiting_gate';
    case BlockedWorker = 'blocked_worker';
    case Completed = 'completed';
    case Failed = 'failed';
}
