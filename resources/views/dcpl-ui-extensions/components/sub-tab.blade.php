{{--
    A single sub-tab inside <x-dcpl::sub-tab-nav>.

    Renders as an <a> when href is given, otherwise a <button>.
    Extra attributes (wire:click, x-on:click, etc.) are passed through.

    Link tab example (navigating between pages):
        <x-dcpl::sub-tab label="Overview"
            :active="request()->routeIs('route.name')"
            href="/your-url" />

    Livewire wire:click tab example:
        <x-dcpl::sub-tab label="Suggest for Purchase"
            :active="$activeFormTab === 'sfp'"
            wire:click="$set('activeFormTab', 'sfp')" />

    Props:
        label  — string — tab label text
        active — bool   — whether this tab is currently selected
        href   — string — optional; if given renders an <a>, otherwise a <button>

    Mirror in dcplibrary/ui when publishing a release.
--}}
@props([
    'label',
    'active' => false,
    'href'   => null,
])

@php
    $base    = 'px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors ';
    $active  = (bool) $active;
    $classes = $base . ($active
        ? 'bg-blue-50 text-blue-700 font-semibold border-blue-600'
        : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50');
@endphp

@if($href)
    <a href="{{ $href }}"
       role="tab"
       {{ $attributes->merge(['class' => $classes]) }}>{{ $label }}</a>
@else
    <button type="button"
            role="tab"
            {{ $attributes->merge(['class' => $classes]) }}>{{ $label }}</button>
@endif
