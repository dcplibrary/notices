@extends('notices::layouts.app')

@section('title', 'Settings')

@section('content')
<div class="px-4 sm:px-0">
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Settings</h2>
            <p class="mt-1 text-sm text-gray-600">
                Manage notification system configuration
            </p>
        </div>
    </div>

    <!-- Settings Cards Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Reference Data Card -->
        <a href="{{ route('notices.settings.reference-data') }}" 
           class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dt class="text-sm font-medium text-gray-500 truncate">
                            Reference Data
                        </dt>
                        <dd class="flex items-baseline">
                            <div class="text-lg font-semibold text-gray-900">
                                Types & Methods
                            </div>
                        </dd>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Enable/disable notification types, delivery methods, and statuses
                    </p>
                </div>
            </div>
        </a>

        <!-- System Settings Card -->
        <div class="bg-white overflow-hidden shadow rounded-lg opacity-50">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dt class="text-sm font-medium text-gray-400 truncate">
                            System Settings
                        </dt>
                        <dd class="flex items-baseline">
                            <div class="text-lg font-semibold text-gray-500">
                                Coming Soon
                            </div>
                        </dd>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500">
                        Configure system-wide notification settings and preferences
                    </p>
                </div>
            </div>
        </div>

        <!-- Sync & Import Card -->
        <a href="{{ route('notices.settings.sync') }}" 
           class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dt class="text-sm font-medium text-gray-500 truncate">
                            Sync & Import
                        </dt>
                        <dd class="flex items-baseline">
                            <div class="text-lg font-semibold text-gray-900">
                                Data Management
                            </div>
                        </dd>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Import data from Polaris and Shoutbomb, test connections
                    </p>
                </div>
            </div>
        </a>

        <!-- Export & Backup Card -->
        <a href="{{ route('notices.settings.export') }}" 
           class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dt class="text-sm font-medium text-gray-500 truncate">
                            Export & Backup
                        </dt>
                        <dd class="flex items-baseline">
                            <div class="text-lg font-semibold text-gray-900">
                                Data Protection
                            </div>
                        </dd>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Export configuration and create database backups
                    </p>
                </div>
            </div>
        </a>

        <!-- Email Settings Card -->
        <div class="bg-white overflow-hidden shadow rounded-lg opacity-50">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dt class="text-sm font-medium text-gray-400 truncate">
                            Email Settings
                        </dt>
                        <dd class="flex items-baseline">
                            <div class="text-lg font-semibold text-gray-500">
                                Coming Soon
                            </div>
                        </dd>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500">
                        Configure email report import and processing
                    </p>
                </div>
            </div>
        </div>

        <!-- FTP Settings Card -->
        <div class="bg-white overflow-hidden shadow rounded-lg opacity-50">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dt class="text-sm font-medium text-gray-400 truncate">
                            FTP Settings
                        </dt>
                        <dd class="flex items-baseline">
                            <div class="text-lg font-semibold text-gray-500">
                                Coming Soon
                            </div>
                        </dd>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500">
                        Configure Shoutbomb FTP connection and paths
                    </p>
                </div>
            </div>
        </div>

        <!-- Dashboard Settings Card -->
        <div class="bg-white overflow-hidden shadow rounded-lg opacity-50">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dt class="text-sm font-medium text-gray-400 truncate">
                            Dashboard Settings
                        </dt>
                        <dd class="flex items-baseline">
                            <div class="text-lg font-semibold text-gray-500">
                                Coming Soon
                            </div>
                        </dd>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500">
                        Configure dashboard display preferences and defaults
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
