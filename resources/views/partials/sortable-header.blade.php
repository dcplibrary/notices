@php
    $isActive = isset($currentSort) && $currentSort === $column;
    $baseClasses = 'inline-flex items-center gap-1 hover:text-dcpl-blue dark:hover:text-sky-300 transition-colors ';
    $classes = $baseClasses . ($isActive ? 'text-dcpl-blue dark:text-sky-300' : 'text-slate-600 dark:text-slate-400');
@endphp

<a href="{{ $sortUrl($column) }}" class="{{ $classes }}">
    <span>{{ $label }}</span>
    @if($isActive)
        <span class="text-xs">{{ $sortIcon($column) }}</span>
    @endif
</a>
