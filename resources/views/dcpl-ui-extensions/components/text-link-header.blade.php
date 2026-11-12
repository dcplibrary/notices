{{--
    Muted text link for the staff / app header row (e.g. “Admin”, custom nav).

    Matches notices user-portal header text links: hidden below md, small gray type.

    Usage:
        <x-dcpl::text-link-header :href="route('admin.dashboard')">Admin</x-dcpl::text-link-header>

    Extra HTML attributes (target, wire:navigate, etc.) merge onto the <a>.

    Mirror in dcplibrary/ui: resources/views/components/text-link-header.blade.php
--}}
@props([
    'href' => '#',
])

<a href="{{ $href }}"
   {{ $attributes->class('hidden md:inline text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors') }}>
    {{ $slot }}
</a>
