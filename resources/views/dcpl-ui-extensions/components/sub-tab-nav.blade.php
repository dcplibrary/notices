{{--
    Wrapping container for a set of sub-tab buttons.

    Usage:
        <x-dcpl::sub-tab-nav label="Form type">
            <x-dcpl::sub-tab label="Tab A" :active="true" href="/url-a" />
            <x-dcpl::sub-tab label="Tab B" :active="false" href="/url-b" />
        </x-dcpl::sub-tab-nav>

    Props:
        label — string — optional aria-label for the <nav> element

    Mirror in dcplibrary/ui when publishing a release.
--}}
@props(['label' => ''])

<div class="bg-white rounded-lg border border-gray-200 mb-4 overflow-hidden">
    <nav class="flex border-b border-gray-200"
         role="tablist"
         @if($label) aria-label="{{ $label }}" @endif>
        {{ $slot }}
    </nav>
</div>
