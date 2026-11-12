<div class="max-w-7xl mx-auto w-full py-2">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="{{ $backUrl ?? route('notices.data.export-files') }}"
           class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Data Browser
        </a>
    </div>

    <!-- Header -->
    <x-notices::card class="dark:bg-slate-800 dark:border-slate-700 mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">
            {{ $title ?? 'Export File' }}
        </h1>
        @if(!empty($filename))
            <div class="text-sm font-mono text-slate-500 dark:text-slate-400 break-all">
                {{ $filename }}
            </div>
        @endif
    </x-notices::card>

    <!-- Content Slot (tables, etc.) -->
    {{ $slot }}
</div>
