{{--
    Primary page-level heading with the DCPL blue left-border accent.

    Usage:
        <x-dcpl::page-heading
            title="Delivery Issues"
            description="Track and resolve notification delivery problems" />

        With trailing content in the slot (badges, action buttons, etc.):
        <x-dcpl::page-heading title="Data Browser">
            <x-dcpl::badge variant="blue">Beta</x-dcpl::badge>
        </x-dcpl::page-heading>

    Props:
        title       — string — required main heading text
        description — string — optional sub-heading / description line

    Mirror in dcplibrary/ui when publishing a release.
--}}
@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'border-l-4 pl-4']) }}
     style="border-color: var(--dcpl-blue, #0075A3);">
    <h1 class="text-3xl font-semibold tracking-tight"
        style="font-family: Outfit, ui-sans-serif, system-ui, sans-serif; color: var(--dcpl-text, #4d6375);">{{ $title }}</h1>
    @if($description)
        <p class="mt-1 text-sm" style="color: #6b7280;">{{ $description }}</p>
    @endif
    @if($slot->isNotEmpty())
        {{ $slot }}
    @endif
</div>
