@extends('notices::layouts.app')

@section('title', 'Display Settings')

@section('body')
<div class="container mx-auto px-4 py-8 max-w-7xl" x-data="{
    activeTable: '{{ $active_table }}',
    editingLabel: {},
    noteModals: {},

    updateLabel(tableName, recordId, customLabel) {
        fetch('{{ route('notices.settings.display.update-label') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                table_name: tableName,
                record_id: recordId,
                custom_label: customLabel
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Label updated successfully');
            }
        })
        .catch(error => console.error('Error:', error));
    },

    toggleEnabled(tableName, recordId, isEnabled) {
        fetch('{{ route('notices.settings.display.toggle-enabled') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                table_name: tableName,
                record_id: recordId,
                is_enabled: isEnabled
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Status updated successfully');
            }
        })
        .catch(error => console.error('Error:', error));
    },

    updateNotes(tableName, recordId, notes) {
        fetch('{{ route('notices.settings.display.update-notes') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                table_name: tableName,
                record_id: recordId,
                notes: notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Notes updated successfully');
            }
        })
        .catch(error => console.error('Error:', error));
    }
}">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('notices.settings.index') }}" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Display Settings</h1>
        </div>
        <p class="text-slate-600 dark:text-slate-400">Customize lookup table labels and control visibility in UI selection inputs</p>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50 rounded-lg p-4 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-dcpl-blue dark:text-sky-300 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-blue-900 dark:text-blue-200">
                <p class="font-semibold mb-1">Original Polaris data is preserved</p>
                <p class="text-blue-800 dark:text-blue-300">Click labels to edit, toggle switches to enable/disable, and note icons to add comments. All changes are saved automatically.</p>
            </div>
        </div>
    </div>

    <!-- Table Navigation (Card Style) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        @foreach($tables as $tableName => $tableData)
        <button
            @click="activeTable = '{{ $tableName }}'"
            :class="activeTable === '{{ $tableName }}' ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'"
            class="p-4 rounded-lg border-2 transition-all text-left group hover:border-blue-400 dark:hover:border-blue-500 shadow-sm"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-dcpl-blue dark:text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">{{ $tableData['label'] }}</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ count($tableData['records']) }} records</p>
                    </div>
                </div>
                <svg x-show="activeTable === '{{ $tableName }}'" class="w-5 h-5 text-dcpl-blue dark:text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </button>
        @endforeach
    </div>

    <!-- Table Content -->
    @foreach($tables as $tableName => $tableData)
    <div x-show="activeTable === '{{ $tableName }}'" x-cloak>
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                <h3 class="text-xl font-semibold text-slate-900 dark:text-white">{{ $tableData['label'] }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-100 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider w-20">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider">Original Label</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider">Custom Label</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider w-32">Enabled</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-slate-600 dark:text-slate-400 uppercase tracking-wider w-20">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($tableData['records'] as $record)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors"
                            x-data="{
                                editing: false,
                                customLabel: '{{ $record['custom_label'] }}',
                                originalCustomLabel: '{{ $record['custom_label'] }}',
                                enabled: {{ $record['is_enabled'] ? 'true' : 'false' }},
                                notes: '{{ addslashes($record['notes'] ?? '') }}',
                                noteModalOpen: false
                            }"
                        >
                            <!-- ID -->
                            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300 font-mono">{{ $record['id'] }}</td>

                            <!-- Original Label -->
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $record['original_label'] }}</td>

                            <!-- Custom Label (Inline Editable) -->
                            <td class="px-6 py-4 text-sm">
                                <!-- Display Mode -->
                                <div x-show="!editing" @click="editing = true" class="cursor-pointer px-3 py-2 -mx-3 -my-2 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors group">
                                    <span class="text-slate-900 dark:text-white" x-text="customLabel || '{{ $record['original_label'] }}'"></span>
                                    <svg class="w-4 h-4 inline-block ml-2 opacity-0 group-hover:opacity-100 text-slate-400 dark:text-slate-500 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                    </svg>
                                    @if($record['custom_label'])
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">Custom</span>
                                    @endif
                                </div>

                                <!-- Edit Mode -->
                                <div x-show="editing" x-cloak class="flex items-center gap-2 -mx-3 -my-2">
                                    <input
                                        type="text"
                                        x-model="customLabel"
                                        @keydown.enter="editing = false; originalCustomLabel = customLabel; updateLabel('{{ $tableName }}', {{ $record['id'] }}, customLabel)"
                                        @keydown.escape="customLabel = originalCustomLabel; editing = false"
                                        x-init="$watch('editing', value => value && setTimeout(() => $el.focus(), 50))"
                                        placeholder="{{ $record['original_label'] }}"
                                        class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                                    >
                                    <button @click="editing = false; originalCustomLabel = customLabel; updateLabel('{{ $tableName }}', {{ $record['id'] }}, customLabel)" class="p-2 text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 rounded transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </button>
                                    <button @click="customLabel = originalCustomLabel; editing = false" class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>

                            <!-- Toggle Switch -->
                            <td class="px-6 py-4 text-center">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        class="sr-only peer"
                                        x-model="enabled"
                                        @change="toggleEnabled('{{ $tableName }}', {{ $record['id'] }}, enabled)"
                                    >
                                    <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-dcpl-blue rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-dcpl-blue"></div>
                                </label>
                            </td>

                            <!-- Notes Icon with Modal -->
                            <td class="px-6 py-4 text-center">
                                <button @click="noteModalOpen = true" class="text-slate-400 dark:text-slate-500 hover:text-dcpl-blue dark:hover:text-sky-300 transition-colors relative">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                                    </svg>
                                    <span x-show="notes" class="absolute -top-1 -right-1 w-2 h-2 bg-dcpl-blue dark:bg-blue-400 rounded-full"></span>
                                </button>

                                <!-- Note Modal -->
                                <div x-show="noteModalOpen" x-cloak @keydown.escape.window="noteModalOpen = false" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                                    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="noteModalOpen = false"></div>
                                    <div class="flex items-center justify-center min-h-screen p-4">
                                        <div @click.away="noteModalOpen = false" class="relative bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-lg w-full p-6 border border-slate-200 dark:border-slate-700">
                                            <div class="flex items-center justify-between mb-4">
                                                <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Edit Note</h3>
                                                <button @click="noteModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="mb-4">
                                                <p class="text-sm text-slate-600 dark:text-slate-400 mb-2"><strong>ID:</strong> {{ $record['id'] }} - {{ $record['original_label'] }}</p>
                                            </div>
                                            <textarea
                                                x-model="notes"
                                                rows="4"
                                                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                                                placeholder="Add a note about this override..."
                                            ></textarea>
                                            <div class="flex justify-end gap-3 mt-4">
                                                <button @click="noteModalOpen = false" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg font-medium transition-colors">
                                                    Cancel
                                                </button>
                                                <button @click="noteModalOpen = false; updateNotes('{{ $tableName }}', {{ $record['id'] }}, notes)" class="px-4 py-2 bg-dcpl-blue hover:brightness-95 text-white rounded-lg font-medium transition-colors shadow-sm">
                                                    Save Note
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
