<div class="flex items-center gap-3">
    @include('notices::partials.dcpl-logo')
    <div>
        <h1 class="text-xl font-semibold tracking-tight">{{ $title }}</h1>
        @if(!empty($subtitle))
            <p class="text-sm text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>
</div>
