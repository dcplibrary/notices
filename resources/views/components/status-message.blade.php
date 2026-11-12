@props(['key', 'html' => false])

@php
    $defaults = config('notices.ui_messages', []);
    $default = $defaults[$key] ?? null;
    $override = \Dcplibrary\Notices\Models\UiMessage::where('key', $key)->first();
    $message = $override->message ?? $default;
    
    // Check if message contains HTML tags
    $containsHtml = $message && preg_match('/<[^>]+>/', $message);
    $renderAsHtml = $html || $containsHtml;
@endphp

@if($message)
    @if($renderAsHtml)
        {{-- Render HTML content - admin-controlled, sanitized with allowed tags --}}
        @php
            // Allow safe HTML tags that admins might use in messages
            $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><span><div><h1><h2><h3><h4><h5><h6><img>';
            $sanitized = strip_tags($message, $allowedTags);
        @endphp
        <div {{ $attributes->merge(['class' => 'text-sm text-slate-700 dark:text-slate-200 prose prose-sm dark:prose-invert max-w-none']) }}>
            {!! $sanitized !!}
        </div>
    @else
        <p {{ $attributes->merge(['class' => 'text-sm text-slate-700 dark:text-slate-200']) }}>
            {{ $message }}
        </p>
    @endif
@endif
