@props(['label', 'value', 'hint' => null, 'icon' => null, 'subColor' => 'success'])

<x-ui.metric-card :label="$label" :value="$value" :sub="$hint" :subColor="$subColor" {{ $attributes }} />
