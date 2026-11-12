{{--
    Delegates to dcplibrary/ui — staff-style card shell (white, border, optional shadow).

    Usage matches x-dcpl::card.
--}}
@props([
    'padding' => 'p-6',
    'shadow'  => true,
])

<x-dcpl::card :padding="$padding" :shadow="$shadow" {{ $attributes }}>
    {{ $slot }}
</x-dcpl::card>
