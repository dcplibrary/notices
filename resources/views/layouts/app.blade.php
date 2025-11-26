<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Notifications Dashboard')</title>

    <!-- Tailwind CSS CDN (for quick styling) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    @stack('styles')

    {{-- Livewire styles (for components like Sync & Import) --}}
    @livewireStyles
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold text-gray-800">Notifications Dashboard</h1>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="/notices"
                           class="@if(request()->routeIs('notices.dashboard')) border-indigo-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Overview
                        </a>
                        <a href="/notices/list"
                           class="@if(request()->routeIs('notices.list')) border-indigo-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Notifications
                        </a>
                        <a href="/notices/shoutbomb"
                           class="@if(request()->routeIs('notices.shoutbomb')) border-indigo-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Shoutbomb
                        </a>
                        <a href="/notices/verification"
                           class="@if(request()->routeIs('notices.verification.*')) border-indigo-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Verification
                        </a>
                        <a href="/notices/troubleshooting"
                           class="@if(request()->routeIs('notices.troubleshooting')) border-indigo-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Troubleshooting
                        </a>
                        @if(Auth::check() && Auth::user()->inGroup('Computer Services'))
                        <a href="/notices/settings"
                           class="@if(request()->routeIs('notices.settings.*')) border-indigo-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Settings
                        </a>
                        @endif
                    </div>
                </div>

                <!-- Right side: User Profile & Help -->
                <div class="hidden sm:flex sm:items-center sm:space-x-4">
                    <!-- Help Icon -->
                    <a href="/notices/help"
                       class="text-gray-600 hover:text-gray-900 inline-flex items-center transition-colors"
                       title="Help & User Guide"
                       aria-label="Help and User Guide"
                       target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke-width="1.5"
                             stroke="currentColor"
                             class="w-6 h-6">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                    </a>

                    <!-- User Profile Dropdown -->
                    @if(Auth::check())
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open"
                                type="button"
                                class="flex items-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 rounded-full"
                                aria-label="User menu"
                                aria-expanded="false">
                            @if(Auth::user()->profile_photo ?? false)
                                <img src="{{ Auth::user()->profile_photo }}"
                                     alt="{{ Auth::user()->name }}"
                                     class="w-10 h-10 rounded-full object-cover border-2 border-gray-200 hover:border-gray-300 transition-colors">
                            @else
                                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold text-sm border-2 border-blue-600 hover:border-blue-700 transition-colors">
                                    @php
                                        $givenName = Auth::user()->givenName ?? '';
                                        $surname = Auth::user()->surname ?? '';

                                        if (!empty($givenName) && !empty($surname)) {
                                            $initials = strtoupper(substr($givenName, 0, 1) . substr($surname, 0, 1));
                                        } else {
                                            $initials = strtoupper(substr(Auth::user()->name ?? 'U', 0, 2));
                                        }
                                    @endphp
                                    {{ $initials }}
                                </div>
                            @endif
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="open"
                             @click.away="open = false"
                             x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg py-1 z-50 border border-gray-200">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <p class="text-sm text-gray-500">Logged in as</p>
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    @php
                                        $givenName = Auth::user()->givenName ?? '';
                                        $surname = Auth::user()->surname ?? '';

                                        if (!empty($givenName) && !empty($surname)) {
                                            $displayName = trim($givenName . ' ' . $surname);
                                        } else {
                                            $displayName = Auth::user()->name ?? Auth::user()->email ?? 'User';
                                        }
                                    @endphp
                                    {{ $displayName }}
                                </p>
                            </div>
                            <a href="/logout"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                Logout
                            </a>
                        </div>
                    </div>
                    <form id="logout-form" action="/logout" method="POST" style="display: none;">
                        @csrf
                    </form>
                    @endif
                </div>

                <div class="flex items-center sm:hidden">
                    <!-- Mobile menu button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" 
                            type="button" 
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                        <span class="sr-only">Open main menu</span>
                        <!-- Icon when menu is closed -->
                        <svg x-show="!mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <!-- Icon when menu is open -->
                        <svg x-show="mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div x-show="mobileMenuOpen"
             x-cloak
             class="sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                <a href="/notices"
                   class="@if(request()->routeIs('notices.dashboard')) bg-indigo-50 border-indigo-500 text-indigo-700 @else border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 @endif block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Overview
                </a>
                <a href="/notices/list"
                   class="@if(request()->routeIs('notices.list')) bg-indigo-50 border-indigo-500 text-indigo-700 @else border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 @endif block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Notifications
                </a>
                <a href="/notices/shoutbomb"
                   class="@if(request()->routeIs('notices.shoutbomb')) bg-indigo-50 border-indigo-500 text-indigo-700 @else border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 @endif block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Shoutbomb
                </a>
                <a href="/notices/verification"
                   class="@if(request()->routeIs('notices.verification.*')) bg-indigo-50 border-indigo-500 text-indigo-700 @else border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 @endif block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Verification
                </a>
                <a href="/notices/troubleshooting"
                   class="@if(request()->routeIs('notices.troubleshooting')) bg-indigo-50 border-indigo-500 text-indigo-700 @else border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 @endif block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Troubleshooting
                </a>
                @if(Auth::check() && Auth::user()->inGroup('Computer Services'))
                <a href="/notices/settings"
                   class="@if(request()->routeIs('notices.settings.*')) bg-indigo-50 border-indigo-500 text-indigo-700 @else border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 @endif block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Settings
                </a>
                @endif
                <a href="/notices/help"
                   target="_blank"
                   class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Help
                </a>
                @if(Auth::check())
                <div class="border-t border-gray-200 pt-4 pb-3">
                    <div class="px-4">
                        <div class="text-sm text-gray-500">Logged in as</div>
                        <div class="text-base font-medium text-gray-800">
                            @php
                                $givenName = Auth::user()->givenName ?? '';
                                $surname = Auth::user()->surname ?? '';

                                if (!empty($givenName) && !empty($surname)) {
                                    $displayName = trim($givenName . ' ' . $surname);
                                } else {
                                    $displayName = Auth::user()->name ?? Auth::user()->email ?? 'User';
                                }
                            @endphp
                            {{ $displayName }}
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="/logout"
                           class="block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800"
                           onclick="event.preventDefault(); document.getElementById('logout-form-mobile').submit();">
                            Logout
                        </a>
                    </div>
                </div>
                <form id="logout-form-mobile" action="/logout" method="POST" style="display: none;">
                    @csrf
                </form>
                @endif
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    {{-- Livewire scripts (for components like Sync & Import) --}}
    @livewireScripts

    @stack('scripts')
</body>
</html>
