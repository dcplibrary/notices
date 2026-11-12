@php
    $logoSvg = null;
    foreach ([
        base_path('vendor/dcplibrary/notices/resources/assets/dcpl_logo.svg'),
        base_path('../notices/resources/assets/dcpl_logo.svg'),
    ] as $logoPath) {
        if (is_readable($logoPath)) {
            $logoSvg = file_get_contents($logoPath);
            break;
        }
    }
@endphp

@if($logoSvg)
    <span class="dcpl-logo inline-block" aria-hidden="true">{!! $logoSvg !!}</span>
@endif
