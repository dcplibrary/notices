{{--
    Preset: help icon in the app header tool row (same chrome as icon-link-header).

    Usage:
        <x-dcpl::help-header :href="asset('help.html')" />
        <x-dcpl::help-header :href="$url" popup-name="noticesHelp" />

    Mirror in dcplibrary/ui: resources/views/components/help-header.blade.php
--}}
@props([
    'href' => '#',
    'label' => 'Help',
    'popupName' => 'dcplHelp',
    'popupFeatures' => 'width=980,height=720,scrollbars=yes',
    'responsive' => true,
])

<x-dcpl::icon-link-header
    :href="$href"
    :label="$label"
    :popup="true"
    :popup-name="$popupName"
    :popup-features="$popupFeatures"
    :responsive="$responsive"
    {{ $attributes }}
>
    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.251 1.09-1.251 1.902v.75m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
    </svg>
</x-dcpl::icon-link-header>
