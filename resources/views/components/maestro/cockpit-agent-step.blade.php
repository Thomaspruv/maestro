@props([
    'agentType' => '',
    'status' => 'pending',
    'cost' => null,
    'runId' => null,
    'outputExists' => false,
    'attempt' => 1,
    'errorMessage' => null,
    'isLast' => false,
])

<div class="flex gap-4 pb-4">
    {{-- Timeline connector --}}
    <div class="flex flex-col items-center">
        {{-- Status circle --}}
        <div class="relative">
            @switch($status)
                @case('pending')
                    <div class="w-12 h-12 rounded-full bg-gray-700 border-2 border-gray-600 flex items-center justify-center">
                        <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                    </div>
                @break
                @case('running')
                    <div class="w-12 h-12 rounded-full bg-blue-900/40 border-2 border-blue-500 flex items-center justify-center animate-pulse">
                        <div class="w-3 h-3 rounded-full bg-blue-400"></div>
                    </div>
                @break
                @case('completed')
                    <div class="w-12 h-12 rounded-full bg-green-900/40 border-2 border-green-500 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                @break
                @case('blocked')
                    <div class="w-12 h-12 rounded-full bg-red-900/40 border-2 border-red-500 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                @break
                @case('waiting_gate')
                    <div class="w-12 h-12 rounded-full bg-amber-900/40 border-2 border-amber-500 flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                @break
                @case('skipped')
                    <div class="w-12 h-12 rounded-full bg-gray-700 border-2 border-gray-600 flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                @break
            @endswitch
        </div>

        {{-- Vertical line connector --}}
        @if(!$isLast)
            <div class="w-1 h-8 mt-2 {{ $status === 'completed' ? 'bg-green-500' : ($status === 'running' ? 'bg-blue-500' : 'bg-gray-600') }}"></div>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex-1 pt-2">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                {{-- Agent name --}}
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="font-semibold text-gray-100 capitalize">{{ str_replace('_', ' ', $agentType) }}</h3>
                    @if($status === 'running')
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-900/40 text-blue-300 border border-blue-500/30">
                            Active
                        </span>
                    @elseif($attempt > 1)
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-900/40 text-amber-300">
                            Attempt {{ $attempt }}
                        </span>
                    @endif
                </div>

                {{-- Status text --}}
                <p class="text-sm text-gray-400">
                    @switch($status)
                        @case('pending')
                            Waiting to start
                        @break
                        @case('running')
                            In progress...
                        @break
                        @case('completed')
                            Completed
                        @break
                        @case('blocked')
                            Failed
                        @break
                        @case('waiting_gate')
                            Waiting for gate review
                        @break
                        @case('skipped')
                            Skipped
                        @break
                    @endswitch
                </p>

                {{-- Error message --}}
                @if($errorMessage)
                    <div class="mt-2 p-2 bg-red-900/20 border border-red-700/30 rounded text-red-300 text-xs">
                        {{ $errorMessage }}
                    </div>
                @endif
            </div>

            {{-- Cost and actions --}}
            <div class="text-right">
                {{-- Cost badge --}}
                @if($cost !== null)
                    <div class="px-3 py-1 rounded bg-amber-900/30 border border-amber-700/30 text-amber-300 text-sm font-medium">
                        ${{ number_format($cost, 4) }}
                    </div>
                @endif

                {{-- View output button --}}
                @if($outputExists && $runId)
                    <button
                        @click="$dispatch('open-output', { runId: {{ $runId }} })"
                        class="mt-2 text-xs text-blue-400 hover:text-blue-300 underline"
                    >
                        View Output
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
