@extends('notices::layouts.app')

@section('content')
<div class="max-w-7xl mx-auto w-full py-2">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Affected Patrons</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Patrons affected by failed delivery contact: <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ $failed_string->delivery_string }}</span>
                </p>
            </div>
            <a href="{{ route('notices.issues', ['type' => 'failed_contacts']) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Failed Contacts
            </a>
        </div>
    </div>

    <!-- Failed String Info Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Contact String -->
            <div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Contact</p>
                <p class="text-lg font-mono text-slate-900 dark:text-white break-all">{{ $failed_string->delivery_string }}</p>
            </div>

            <!-- Type -->
            <div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Type</p>
                @if($failed_string->string_type === 'email')
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-700/50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                        </svg>
                        Email
                    </span>
                @elseif($failed_string->string_type === 'phone')
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-700/50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        Phone
                    </span>
                @endif
            </div>

            <!-- Affected Patrons -->
            <div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Affected Patrons</p>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $failed_string->affected_patron_count }}</p>
            </div>

            <!-- Total Failures -->
            <div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Total Failures</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $failed_string->total_failures }}</p>
            </div>
        </div>

        <!-- Date Range -->
        <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                First failed: <span class="font-medium text-slate-900 dark:text-white">{{ \Carbon\Carbon::parse($failed_string->first_failed_at)->format('M j, Y g:i A') }}</span>
                <span class="mx-2">•</span>
                Last failed: <span class="font-medium text-slate-900 dark:text-white">{{ \Carbon\Carbon::parse($failed_string->last_failed_at)->format('M j, Y g:i A') }}</span>
            </p>
        </div>
    </div>

    @if($affected_patrons->isEmpty())
        <!-- Empty State -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-green-500 dark:text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">No Active Issues</h3>
            <p class="text-slate-600 dark:text-slate-400">
                All issues for this contact have been resolved.
            </p>
        </div>
    @else
        <!-- Affected Patrons Table -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Patron Issues</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    {{ $affected_patrons->count() }} patron(s) experiencing delivery issues
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Issue ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Patron Barcode</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Notification</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detected</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($affected_patrons as $issue)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <!-- Issue ID -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-mono text-slate-900 dark:text-white">{{ $issue->id }}</span>
                                </td>

                                <!-- Patron Barcode -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('notices.patron', $issue->patron_barcode) }}" class="text-sm font-medium text-dcpl-blue dark:text-sky-300 hover:underline">
                                        {{ $issue->patron_barcode }}
                                    </a>
                                </td>

                                <!-- Notification -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-mono text-slate-900 dark:text-white">
                                        #{{ $issue->notification_id }}
                                    </span>
                                </td>

                                <!-- Detected -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-slate-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($issue->detected_at)->format('M j, Y g:i A') }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button
                                        @click="$refs['resolveModal{{ $issue->id }}'].showModal()"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 dark:bg-green-500 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Resolve
                                    </button>

                                    <!-- Resolve Modal -->
                                    <dialog x-ref="resolveModal{{ $issue->id }}" class="backdrop:bg-black/50 bg-white dark:bg-slate-800 rounded-lg shadow-xl p-0 max-w-md w-full">
                                        <div class="p-6">
                                            <div class="flex items-center justify-between mb-4">
                                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Resolve Issue #{{ $issue->id }}</h3>
                                                <button @click="$refs['resolveModal{{ $issue->id }}'].close()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>

                                            <form method="POST" action="{{ route('notices.issues.resolve', $issue->id) }}">
                                                @csrf
                                                <div class="mb-4">
                                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                                        Resolution Notes <span class="text-red-500">*</span>
                                                    </label>
                                                    <textarea
                                                        name="resolution_notes"
                                                        rows="4"
                                                        required
                                                        class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                                                        placeholder="Describe how this issue was resolved..."
                                                    ></textarea>
                                                </div>

                                                <div class="flex gap-3 justify-end">
                                                    <button
                                                        type="button"
                                                        @click="$refs['resolveModal{{ $issue->id }}'].close()"
                                                        class="px-4 py-2 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors"
                                                    >
                                                        Cancel
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        class="px-4 py-2 bg-green-600 dark:bg-green-500 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors"
                                                    >
                                                        Resolve Issue
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </dialog>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50 rounded-lg p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-dcpl-blue dark:text-sky-300 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-300 mb-1">Bulk Resolution</h4>
                        <p class="text-sm text-blue-800 dark:text-blue-400">
                            You can resolve all issues for this failed contact at once using the bulk resolve feature.
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('notices.issues.bulk-resolve-form') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-dcpl-blue text-white rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors whitespace-nowrap"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Bulk Resolve
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
