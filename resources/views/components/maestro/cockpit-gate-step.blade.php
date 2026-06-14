@props([
    'gateId' => null,
    'gateType' => 'specs_review',
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
                        <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
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
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="font-semibold text-gray-100 capitalize">
                        @switch($gateType)
                            @case('specs_review')
                                Specs Review Gate
                            @break
                            @case('tech_review')
                                Technical Review Gate
                            @break
                            @case('merge_review')
                                Merge Review Gate
                            @break
                            @default
                                {{ str_replace('_', ' ', $gateType) }}
                        @endswitch
                    </h3>
                </div>

                {{-- Status text --}}
                <p class="text-sm text-gray-400">
                    @switch($status)
                        @case('pending')
                            Awaiting review
                        @break
                        @case('approved')
                            Approved
                        @break
                        @case('rejected')
                            Rejected
                        @break
                    @endswitch
                </p>

                {{-- Feedback --}}
                @if($feedback)
                    <div class="mt-2 p-2 bg-gray-700/30 border border-gray-600/50 rounded text-gray-300 text-xs">
                        <span class="font-medium text-gray-400">Feedback:</span> {{ $feedback }}
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            @if($status === 'pending' && $gateId)
                <div class="flex gap-2">
                    <button
                        wire:click="approveGate({{ $gateId }})"
                        class="px-3 py-1 text-sm rounded bg-green-900/40 text-green-300 hover:bg-green-900/60 border border-green-700/50 transition-colors"
                    >
                        Approve
                    </button>
                    <button
                        wire:click="rejectGate({{ $gateId }})"
                        class="px-3 py-1 text-sm rounded bg-red-900/40 text-red-300 hover:bg-red-900/60 border border-red-700/50 transition-colors"
                    >
                        Reject
                    </button>
                </div>
            @elseif($status === 'approved')
                <span class="inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-green-900/40 text-green-300 border border-green-700/50">
                    ✓ Approved
                </span>
            @elseif($status === 'rejected')
                <span class="inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-red-900/40 text-red-300 border border-red-700/50">
                    ✗ Rejected
                </span>
            @endif
        </div>
    </div>
</div>
