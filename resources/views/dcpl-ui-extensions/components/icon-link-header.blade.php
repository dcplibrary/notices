{{--
    Circular header action (requests / DevTools parity: h-10, border-gray-300, bg-gray-50, shadow-sm).

    Usage:
        <x-dcpl::icon-link-header href="{{ route('help') }}" label="Help">
            <svg>...</svg>
        </x-dcpl::icon-link-header>

    Popup help window:
        <x-dcpl::icon-link-header
            href="{{ $url }}"
            label="Help"
            :popup="true"
            popup-name="myAppHelp"
        >
            ...
        </x-dcpl::icon-link-header>

    Props:
        href           — string  — URL
        label          — string  — aria-label (required for icon-only)
        popup          — bool    — when true, opens href in a sized window (target=_blank + onclick)
        popupName      — string  — window name for window.open
        popupFeatures  — string  — features string for window.open
        responsive     — bool    — when true (default), hidden below md like other header chrome

    Mirror in dcplibrary/ui: resources/views/components/icon-link-header.blade.php
--}}
@props([
    'href' => '#',
    'label' => '',
    'popup' => false,
    'popupName' => 'dcplHelp',
    'popupFeatures' => 'width=980,height=720,scrollbars=yes',
    'responsive' => true,
])

@php
    $chrome = 'h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-300 bg-gray-50 text-gray-600 shadow-sm transition-colors duration-150 hover:border-gray-400 hover:bg-gray-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:shadow-none dark:hover:border-slate-500 dark:hover:bg-slate-700';
    $display = $responsive ? 'hidden md:inline-flex ' . $chrome : 'inline-flex ' . $chrome;
@endphp

<a href="{{ $href }}"
   {{ $attributes->class($display) }}
   aria-label="{{ $label }}"
   @if($popup)
       target="_blank"
       rel="noopener noreferrer"
       onclick="try { window.open(this.href, @js($popupName), @js($popupFeatures)); return false; } catch (e) { return true; }"
   @endif
>
    {{ $slot }}
</a>
