@extends('notices::layouts.app')

@section('title', 'Settings')

@section('body')
<div class="notices-staff-page pb-10 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="mb-8 border-l-4 border-dcpl-blue pl-4">
        <h1 class="font-outfit text-3xl font-semibold tracking-tight text-dcpl-text mb-1">Settings</h1>
        <p class="text-sm text-gray-600">System-wide configuration options</p>
    </div>

    <!-- Settings Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        <!-- Display Settings -->
        <a href="{{ route('notices.settings.display') }}" class="group h-full">
            <div class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-lg p-6 transition-all duration-200 shadow-sm h-full flex flex-col">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-dcpl-blue/10 text-dcpl-blue dark:text-sky-300 group-hover:bg-dcpl-blue/15 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500 group-hover:text-slate-900 dark:group-hover:text-slate-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Display Settings</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                    Customize how lookup table values are displayed and control which options appear in selection inputs
                </p>

                <div class="flex items-center gap-4 text-sm mt-auto">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-blue-500 dark:bg-blue-500"></div>
                        <span class="text-slate-700 dark:text-slate-300">{{ $lookup_override_count }} overrides</span>
                    </div>
                    @if($disabled_lookup_count > 0)
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-amber-500 dark:bg-yellow-500"></div>
                        <span class="text-slate-700 dark:text-slate-300">{{ $disabled_lookup_count }} disabled</span>
                    </div>
                    @endif
                </div>
            </div>
        </a>

        <!-- UI Messages -->
        <a href="{{ route('notices.settings.messages') }}" class="group h-full">
            <div class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-lg p-6 transition-all duration-200 shadow-sm h-full flex flex-col">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/50 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h3l1-1h2l1 1h3a2 2 0 012 2v12a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500 group-hover:text-slate-900 dark:group-hover:text-slate-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">UI Messages</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                    Configure reusable status and help messages that appear in issues, detail modals, and other UI components.
                </p>

                <div class="flex items-center gap-2 text-sm mt-auto">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-500"></div>
                    <span class="text-slate-700 dark:text-slate-300">Centralize user-facing messages</span>
                </div>
            </div>
        </a>

        <!-- Activity Log -->
        <a href="{{ route('notices.settings.activity') }}" class="group h-full">
            <div class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-lg p-6 transition-all duration-200 shadow-sm h-full flex flex-col">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-sky-50 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 group-hover:bg-sky-100 dark:group-hover:bg-sky-900/50 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500 group-hover:text-slate-900 dark:group-hover:text-slate-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Activity Log</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                    Review notification and user activity over time, including imports, reconciliations, and manual actions.
                </p>

                <div class="flex items-center gap-2 text-sm mt-auto">
                    <div class="w-2 h-2 rounded-full bg-sky-500 dark:bg-sky-500"></div>
                    <span class="text-slate-700 dark:text-slate-300">Audit system behavior</span>
                </div>
            </div>
        </a>

        <!-- Maintenance Tools -->
        <a href="{{ route('notices.settings.maintenance') }}" class="group h-full">
            <div class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-lg p-6 transition-all duration-200 shadow-sm h-full flex flex-col">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 group-hover:bg-amber-100 dark:group-hover:bg-amber-900/50 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582a2 2 0 011.789 1.106l1.07 2.14A2 2 0 0010.21 14H15m4-10v5h-.582a2 2 0 00-1.789 1.106l-1.07 2.14A2 2 0 0113.79 14H9" />
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500 group-hover:text-slate-900 dark:group-hover:text-slate-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Maintenance</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                    Run safe maintenance tasks like cleaning up cached import/export files from the web interface.
                </p>

                <div class="flex items-center gap-2 text-sm mt-auto">
                    <div class="w-2 h-2 rounded-full bg-amber-500 dark:bg-amber-500"></div>
                    <span class="text-slate-700 dark:text-slate-300">Admin-only tools</span>
                </div>
            </div>
        </a>

        <!-- API Tokens -->
        <a href="{{ route('notices.settings.api-tokens') }}" class="group h-full">
            <div class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-lg p-6 transition-all duration-200 shadow-sm h-full flex flex-col">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 group-hover:bg-purple-100 dark:group-hover:bg-purple-900/50 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500 group-hover:text-slate-900 dark:group-hover:text-slate-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">API Tokens</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                    Generate and manage API tokens for accessing the Notices API. Create tokens for external dashboards, scripts, or integrations.
                </p>

                <div class="flex items-center gap-4 text-sm mt-auto">
                    @if($api_token_count > 0)
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-purple-500 dark:bg-purple-500"></div>
                        <span class="text-slate-700 dark:text-slate-300">{{ $api_token_count }} token{{ $api_token_count !== 1 ? 's' : '' }}</span>
                    </div>
                    @endif
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-purple-500 dark:bg-purple-500"></div>
                        <span class="text-slate-700 dark:text-slate-300">Manage API access</span>
                    </div>
                </div>
            </div>
        </a>

        <!-- Jobs -->
        <a href="{{ route('notices.settings.jobs') }}" class="group h-full">
            <div class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-lg p-6 transition-all duration-200 shadow-sm h-full flex flex-col">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-slate-50 dark:bg-slate-900/30 text-slate-700 dark:text-slate-300 group-hover:bg-slate-100 dark:group-hover:bg-slate-900/50 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500 group-hover:text-slate-900 dark:group-hover:text-slate-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Jobs</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
                    Monitor queued imports and maintenance actions, view logs, and cancel when needed.
                </p>

                <div class="flex items-center gap-2 text-sm mt-auto">
                    <div class="w-2 h-2 rounded-full {{ ($active_job_run_count ?? 0) > 0 ? 'bg-blue-500' : 'bg-slate-400' }}"></div>
                    <span class="text-slate-700 dark:text-slate-300">{{ $active_job_run_count ?? 0 }} active</span>
                </div>
            </div>
        </a>

    </div>

    <!-- Information Box -->
    <div class="mt-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50 rounded-lg p-6">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-dcpl-blue dark:text-sky-300 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h4 class="text-blue-900 dark:text-blue-200 font-semibold mb-1">About Settings</h4>
                <p class="text-blue-800 dark:text-blue-300 text-sm">
                    These settings affect how the system displays data to all users. Changes made here are system-wide and will be visible to everyone using the notification system.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
