@php
    $noticesAuthUser = auth()->user();
    $noticesIsAdmin = $noticesAuthUser && (method_exists($noticesAuthUser, 'isAdmin')
        ? $noticesAuthUser->isAdmin()
        : (($noticesAuthUser->role ?? null) === 'admin'));

    $noticesLogoutRoute = \Illuminate\Support\Facades\Route::has('entra.logout')
        ? route('entra.logout')
        : (\Illuminate\Support\Facades\Route::has('logout') ? route('logout') : null);

    $noticesNavItems = [
        [
            'label'  => 'Overview',
            'href'   => route('notices.dashboard'),
            'active' => request()->routeIs('notices.dashboard'),
        ],
        [
            'label'  => 'Notifications',
            'href'   => route('notices.index'),
            'active' => request()->routeIs('notices.index'),
        ],
        [
            'label'  => 'Issues',
            'href'   => route('notices.issues'),
            'active' => request()->routeIs('notices.issues*'),
        ],
        [
            'label'  => 'Data',
            'href'   => route('notices.data.search'),
            'active' => request()->routeIs('notices.data.*'),
        ],
    ];
@endphp

{{-- Same shell as ../requests/resources/views/staff/_layout.blade.php — x-dcpl::layouts.staff --}}
<x-dcpl::layouts.staff
    :logo-href="route('notices.dashboard')"
    :logout-route="$noticesLogoutRoute"
    :settings-route="$noticesIsAdmin ? route('notices.settings.index') : null"
>
    <x-slot:package-css>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="stylesheet" href="{{ route('notices.assets.css') }}?v={{ $noticesCssVersion ?? 'dev' }}">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @stack('head')
    </x-slot:package-css>

    <x-slot:nav>
        @foreach($noticesNavItems as $item)
            <x-dcpl::staff-nav-tab
                :href="$item['href']"
                :label="$item['label']"
                :active="$item['active']"
            />
        @endforeach
    </x-slot:nav>

    @if($noticesIsAdmin)
        <x-slot:dropdown-items>
            <a href="{{ route('notices.user-portal.index') }}"
               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Viewer
            </a>
        </x-slot:dropdown-items>
    @endif

    <div class="notices-staff-scope">
        @yield('body')
    </div>

    <footer class="mt-16 border-t border-gray-200 py-8">
        <div class="container mx-auto px-4 text-center text-dcpl-text text-sm">
            <p>DC Public Library &mdash; {{ config('app.name', 'Laravel') }}</p>
            <p class="mt-1 text-gray-400">Powered by Laravel {{ app()->version() }}</p>
        </div>
    </footer>

    @stack('scripts')
</x-dcpl::layouts.staff>
