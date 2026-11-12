@extends('notices::layouts.app')

@section('content')
<div class="max-w-7xl mx-auto w-full py-2">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Failed Delivery Contacts</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Email addresses and phone numbers that are causing delivery failures
                </p>
            </div>
            <a href="{{ route('notices.issues') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Issues
            </a>
        </div>
    </div>

    @if($failed_strings->isEmpty())
        <!-- Empty State -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-green-500 dark:text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">No Failed Contacts</h3>
            <p class="text-slate-600 dark:text-slate-400">
                There are currently no email addresses or phone numbers experiencing delivery failures.
            </p>
        </div>
    @else
        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Total Failed Strings -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Failed Contacts</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $failed_strings->count() }}</p>
                    </div>
                    <div class="p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Affected Patrons -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Affected Patrons</p>
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $failed_strings->sum('affected_patron_count') }}</p>
                    </div>
                    <div class="p-3 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Failures -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Total Failures</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $failed_strings->sum('total_failures') }}</p>
                    </div>
                    <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                        <svg class="w-6 h-6 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Failed Strings Table -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Contact & Patron</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Delivery Method</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Stats</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($failed_strings as $failedString)
                            @php
                                $isPhone = ($failedString->string_type === 'phone') || \in_array($failedString->delivery_option_id, [3, 8], true);
                                $labelContact = $isPhone ? 'Phone' : 'Email';
                            @endphp
                            <tr
                                class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer"
                                @click="$dispatch('failed-contact-open', { id: {{ $failedString->id }} })"
                            >
                                <!-- Contact & Patron -->
                                <td class="px-6 py-4 align-top">
                                    <div class="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400">
                                        <div class="flex flex-wrap gap-6">
                                            <div>{{ $labelContact }}</div>
                                            <div>Barcode</div>
                                            <div>Name</div>
                                        </div>
                                        <div class="flex flex-wrap gap-6 text-slate-900 dark:text-white">
                                            <div class="font-mono text-sm break-all">{{ $failedString->delivery_string }}</div>
                                            <div class="font-mono text-sm">
                                                @if($failedString->example_patron_barcode ?? false)
                                                    {{ $failedString->example_patron_barcode }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                            <div class="text-sm">
                                                @if(($failedString->example_patron_last_name ?? null) || ($failedString->example_patron_first_name ?? null))
                                                    {{ $failedString->example_patron_last_name ?? '' }}@if(($failedString->example_patron_last_name ?? null) && ($failedString->example_patron_first_name ?? null)), @endif{{ $failedString->example_patron_first_name ?? '' }}
                                                @else
                                                    Unknown patron
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Type -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($failedString->string_type === 'email')
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-700/50">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                            </svg>
                                            Email
                                        </span>
                                    @elseif($failedString->string_type === 'phone')
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-700/50">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                            Phone
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-50 dark:bg-slate-900/30 text-slate-700 dark:text-slate-400 border border-slate-200 dark:border-slate-700/50">
                                            {{ ucfirst($failedString->string_type) }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Delivery Method -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($failedString->deliveryOption)
                                        <div class="flex items-center gap-2">
                                            @if(stripos($failedString->deliveryOption->delivery_option, 'mail') !== false)
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                </svg>
                                            @elseif(stripos($failedString->deliveryOption->delivery_option, 'email') !== false)
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                                </svg>
                                            @elseif(stripos($failedString->deliveryOption->delivery_option, 'voice') !== false || stripos($failedString->deliveryOption->delivery_option, 'phone') !== false)
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                            @elseif(stripos($failedString->deliveryOption->delivery_option, 'sms') !== false)
                                                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                                </svg>
                                            @endif
                                            <span class="text-sm text-slate-900 dark:text-white">{{ $failedString->deliveryOption->delivery_option }}</span>
                                        </div>
                                    @else
                                        <span class="text-sm text-slate-500 dark:text-slate-400">N/A</span>
                                    @endif
                                </td>

                                <!-- Stats (failures + dates) -->
                                <td class="px-6 py-4 align-top whitespace-nowrap">
                                    <div class="text-xs text-slate-600 dark:text-slate-400">
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $failedString->affected_patron_count }}</span> patron(s)
                                        • {{ $failedString->total_failures }} failure{{ $failedString->total_failures === 1 ? '' : 's' }}
                                        <br>
                                        <span>
                                            First: {{ \Carbon\Carbon::parse($failedString->first_failed_at)->format('M j, Y') }}
                                            • Last: {{ \Carbon\Carbon::parse($failedString->last_failed_at)->format('M j, Y') }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm align-top">
                                    <a
                                        href="{{ route('notices.affected-patrons', $failedString->id) }}"
                                        @click.stop
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                        </svg>
                                        View patrons
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
    <!-- Failed Contact Edit/Resolve Modal -->
    <x-notices::failed-contact-modal />
</div>
@endsection
