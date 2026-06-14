@props([
    'cost' => 0,
    'isActive' => false,
    'size' => 'md',
])

@switch($size)
    @case('sm')
        <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-900/30 text-amber-300 border border-amber-700/30 gap-1">
            <span class="text-amber-400">$</span>{{ number_format($cost, 4) }}
            @if($isActive)
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
            @endif
        </div>
    @break
    @case('lg')
        <div class="text-right">
            <div class="text-sm text-gray-400">Total Cost</div>
            <div class="text-3xl font-bold text-amber-400">
                ${{ number_format($cost, 4) }}
            </div>
            @if($isActive)
                <div class="text-xs text-blue-400 mt-1 animate-pulse">
                    Pipeline running...
                </div>
            @endif
        </div>
    @break
    @default
        <div class="px-3 py-1 rounded bg-amber-900/30 border border-amber-700/30 text-amber-300 text-sm font-medium">
            ${{ number_format($cost, 4) }}
            @if($isActive)
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse ml-2"></span>
            @endif
        </div>
@endswitch
