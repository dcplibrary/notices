@php
    $portalUser = auth()->user();
    $portalIsAdmin = $portalUser && (method_exists($portalUser, 'isAdmin')
        ? $portalUser->isAdmin()
        : (($portalUser->role ?? null) === 'admin'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ theme: localStorage.getItem('theme') || 'system' }"
      x-init="$watch('theme', value => {
          localStorage.setItem('theme', value);
          const isDark = value === 'dark' || (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
          document.documentElement.classList.toggle('dark', isDark);
      })">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Notification Status') &mdash; {{ config('app.name') }}</title>

    <script>
        (function () {
            const theme = localStorage.getItem('theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ route('dcpl.ui.css') }}?v={{ $dcplUiCssVersion ?? 'dev' }}">
    <link rel="stylesheet" href="{{ route('notices.assets.css') }}?v={{ $noticesCssVersion ?? 'dev' }}">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @stack('head')
</head>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen font-outfit text-dcpl-text dark:text-slate-100">

{{-- Same staff chrome as notices::layouts.app (dcplibrary/ui) — no CDN Tailwind, no gradient avatar --}}
<header class="w-full bg-white shadow-sm dark:bg-slate-900 dark:shadow-slate-900/50">
    <div class="px-6 py-3 flex items-center justify-between border-b border-gray-200 dark:border-slate-700">
        <div class="flex items-center gap-4 min-w-0">
            <x-dcpl::logo :href="route('notices.user-portal.index')" :show-name="false" />
            <span class="font-outfit text-2xl font-light tracking-widest uppercase text-dcpl-text dark:text-slate-300 select-none truncate">
                {{ config('app.name') }}
            </span>
        </div>

        <div class="flex items-center gap-3 shrink-0">

            @auth
                @if($portalIsAdmin)
                    <x-dcpl::text-link-header :href="route('notices.dashboard')">
                        Admin
                    </x-dcpl::text-link-header>
                    <x-dcpl::settings-header :href="route('notices.settings.index')" />
                @endif
            @endauth

            <x-dcpl::help-header
                :href="route('notices.viewer-help')"
                popup-name="dcplNoticesHelp"
            />

            @guest
                <x-dcpl::avatar name="Guest" size="md" />
            @endguest
            @auth
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button type="button"
                            @click="open = !open"
                            class="hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-offset-1 rounded-full dark:focus:ring-offset-slate-900"
                            style="--tw-ring-color: var(--dcpl-blue, #0075A3)">
                        <x-dcpl::avatar :name="$portalUser->name ?? ''" size="md" />
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-56 origin-top-right rounded-lg bg-white dark:bg-slate-900 shadow-lg ring-1 ring-gray-900/10 dark:ring-slate-100/10 z-50"
                         style="display:none">

                        <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-800">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $portalUser->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ $portalUser->email }}</p>
                            @if($portalUser->role ?? null)
                                <p class="text-xs font-medium mt-1" style="color: var(--dcpl-blue, #0075A3)">{{ ucfirst($portalUser->role) }}</p>
                            @endif
                        </div>

                        <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-800">
                            <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase mb-2">Theme</p>
                            <div class="flex gap-1">
                                <button type="button" @click="theme = 'light'" :class="theme === 'light' ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-slate-400'" class="flex-1 px-2 py-1.5 rounded text-xs font-medium">Light</button>
                                <button type="button" @click="theme = 'dark'" :class="theme === 'dark' ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-slate-400'" class="flex-1 px-2 py-1.5 rounded text-xs font-medium">Dark</button>
                                <button type="button" @click="theme = 'system'" :class="theme === 'system' ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-slate-400'" class="flex-1 px-2 py-1.5 rounded text-xs font-medium">Auto</button>
                            </div>
                        </div>

                        @if($portalIsAdmin)
                            <div class="py-1 border-b border-gray-100 dark:border-slate-800">
                                <a href="{{ route('notices.dashboard') }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                    </svg>
                                    Staff dashboard
                                </a>
                            </div>
                        @endif

                        <div class="py-1">
                            <form method="POST" action="{{ route('entra.logout') }}">
                                @csrf
                                <button type="submit"
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endauth
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8">
    @if(session('success'))
        <div class="mb-4 flex items-center gap-3 rounded-lg border-l-4 border-dcpl-green bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-800 dark:text-green-200" role="alert">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 flex items-center gap-3 rounded-lg border-l-4 border-red-500 bg-red-50 dark:bg-red-900/30 px-4 py-3 text-red-800 dark:text-red-200" role="alert">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @yield('body')
</main>

<footer class="mt-16 border-t border-gray-200 dark:border-slate-800 py-8">
    <div class="container mx-auto px-4 text-center text-sm text-dcpl-text dark:text-slate-400">
        <p>Daviess County Public Library &mdash; {{ config('app.name', 'Notices') }}</p>
    </div>
</footer>

@stack('scripts')
</body>
</html>
