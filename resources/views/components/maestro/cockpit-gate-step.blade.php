@props([
    'gateId' => null,
    'gateType' => 'specs',
    'status' => 'pending',
    'feedback' => null,
    'isLast' => false,
])

<div class="flex gap-4 pb-4">
    {{-- Timeline connector --}}
    <div class="flex flex-col items-center">
        {{-- Status circle --}}
        <div class="relative">
            @switch($status)
                @case('pending')
                    <div class="w-12 h-12 rounded-full bg-amber-900/40 border-2 border-amber-500 flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"></path>
                        </svg>
                    </div>
                @break
                @case('approved')
                    <div class="w-12 h-12 rounded-full bg-green-900/40 border-2 border-green-500 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                @break
                @case('rejected')
                    <div class="w-12 h-12 rounded-full bg-red-900/40 border-2 border-red-500 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                @break
            @endswitch
        </div>

        {{-- Vertical line connector --}}
        @if(!$isLast)
            <div class="w-1 h-8 mt-2 {{ $status === 'approved' ? 'bg-green-500' : ($status === 'pending' ? 'bg-amber-500' : 'bg-red-500') }}"></div>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex-1 pt-2">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                {{-- Gate name --}}
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="font-semibold text-gray-100">
                        @switch($gateType)
                            @case('specs_review')
                                Specs Review Gate
                            @break
                            @case('tech_review')
                                Tech Review Gate
                            @break
                            @case('merge_review')
                                Merge Review Gate
                            @break
                            @default
                                Review Gate
                        @endswitch
                    </h3>
                    @if($status === 'pending')
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-900/40 text-amber-300 border border-amber-500/30 animate-pulse">
                            Action Required
                        </span>
                    @endif
                </div>

                {{-- Status text --}}
                <p class="text-sm text-gray-400">
                    @switch($status)
                        @case('pending')
                            Waiting for review...
                        @break
                        @case('approved')
                            Approved - pipeline continues
                        @break
                        @case('rejected')
                            Rejected - agent will re-run
                        @break
                    @endswitch
                </p>

                {{-- Feedback --}}
                @if($feedback)
                    <div class="mt-2 p-2 bg-gray-700/40 border border-gray-600/30 rounded text-gray-300 text-xs italic">
                        <span class="font-semibold">Feedback:</span> {{ $feedback }}
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            @if($status === 'pending' && $gateId)
                <div class="flex gap-2">
                    <button
                        wire:click="approveGate({{ $gateId }})"
                        class="px-4 py-2 rounded bg-green-900/50 hover:bg-green-900/70 border border-green-600/50 text-green-300 text-sm font-medium transition-colors"
                    >
                        Approve
                    </button>
                    <button
                        wire:click="rejectGate({{ $gateId }})"
                        class="px-4 py-2 rounded bg-red-900/50 hover:bg-red-900/70 border border-red-600/50 text-red-300 text-sm font-medium transition-colors"
                    >
                        Reject
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
