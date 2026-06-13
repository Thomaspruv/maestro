@props([
    'redirect' => null,
    'compact' => false,
])

<livewire:github-connect
    :compact="$compact"
    :redirect="$redirect ?? url()->current()"
/>
