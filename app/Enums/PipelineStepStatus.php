<?php

namespace App\Enums;

enum PipelineStepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case WaitingGate = 'waiting_gate';
    case Skipped = 'skipped';
}
