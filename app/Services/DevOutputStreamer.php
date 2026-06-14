<?php

namespace App\Services;

use App\Events\AgentRunUpdated;
use App\Models\AgentRun;

class DevOutputStreamer
{
    public function flush(AgentRun $run, string $output, bool $force = false): void
    {
        static $lastFlush = [];

        $runId = $run->id;
        $now = microtime(true);
        $previous = $lastFlush[$runId] ?? 0.0;

        if (! $force && ($now - $previous) < 2.0) {
            return;
        }

        $run->update(['output' => $output]);
        broadcast(new AgentRunUpdated($run->fresh()));
        $lastFlush[$runId] = $now;
    }
}
