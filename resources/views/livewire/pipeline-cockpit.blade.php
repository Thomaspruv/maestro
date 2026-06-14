<div class="space-y-6" @if($shouldPoll) wire:poll.5s="refreshSnapshot" @endif>
    {{-- Header with title and cost badge --}}
    <div class="flex items-start justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-100">Pipeline Cockpit</h2>
            <p class="text-gray-400 text-sm mt-1">Task: {{ $task->title }}</p>
        </div>

        <div class="text-right">
            <div class="text-sm text-gray-400">Total Cost</div>
            <div class="text-2xl font-bold text-amber-400">
                ${{ number_format($snapshot['total_cost'] ?? 0, 4) }}
            </div>
            @if($snapshot['is_active'] ?? false)
                <div class="text-xs text-blue-400 mt-1 animate-pulse">
                    Pipeline running...
                </div>
            @endif
        </div>
    </div>

    {{-- Pipeline not started state --}}
    @if(($snapshot['steps'] ?? []) === [])
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-8 text-center">
            <div class="text-gray-400 mb-4">
                <svg class="w-12 h-12 mx-auto text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-gray-300 font-medium">Pipeline not started</p>
            <p class="text-gray-500 text-sm mt-2">Start the pipeline to see progress in real-time</p>
        </div>
    @else
        {{-- Pipeline steps timeline --}}
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-8">
            <div class="space-y-1">
                @foreach($snapshot['steps'] ?? [] as $index => $step)
                    @if($step['type'] === 'agent')
                        <x-maestro.cockpit-agent-step
                            :agent-type="$step['agent_type']"
                            :status="$step['status']"
                            :cost="$step['cost']"
                            :run-id="$step['run_id']"
                            :output-exists="$step['output_exists'] ?? false"
                            :attempt="$step['attempt'] ?? 1"
                            :error-message="$step['error_message'] ?? null"
                            :is-last="$index === count($snapshot['steps']) - 1"
                            @open-output="openAgentOutput"
                        />
                    @elseif($step['type'] === 'gate')
                        <x-maestro.cockpit-gate-step
                            :gate-id="$step['gate_id']"
                            :gate-type="$step['gate_type']"
                            :status="$step['status']"
                            :feedback="$step['feedback'] ?? null"
                            :is-last="$index === count($snapshot['steps']) - 1"
                            @approve-gate="approveGate"
                            @reject-gate="rejectGate"
                        />
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Final status --}}
        @if($snapshot['status'] === 'done')
            <div class="bg-green-900/20 border border-green-700/50 rounded-lg p-4 flex items-start gap-3">
                <svg class="w-5 h-5 text-green-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <div>
                    <p class="text-green-300 font-medium">Pipeline completed</p>
                    <p class="text-green-400 text-sm">All agents executed successfully</p>
                </div>
            </div>
        @endif
    @endif
</div>

@script
<script>
    Livewire.on('open-agent-output', (data) => {
        window.dispatchEvent(new CustomEvent('open-agent-output', { detail: data }));
    });
</script>
@endscript
