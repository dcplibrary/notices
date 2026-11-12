@extends('notices::layouts.app')

@section('title', 'Status Messages')

@push('head')
<!-- Trix Editor (free, no API key required) -->
<link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
<style>
    /* Dark mode support for Trix */
    .dark trix-editor {
        background-color: rgb(15 23 42); /* slate-900 */
        color: rgb(241 245 249); /* slate-100 */
        border-color: rgb(51 65 85); /* slate-700 */
    }
    .dark trix-toolbar {
        background-color: rgb(30 41 59); /* slate-800 */
        border-color: rgb(51 65 85); /* slate-700 */
    }
    .dark trix-toolbar .trix-button {
        background-color: rgb(30 41 59);
        color: rgb(203 213 225); /* slate-300 */
        border-color: rgb(51 65 85);
    }
    .dark trix-toolbar .trix-button:hover {
        background-color: rgb(51 65 85);
    }
    .dark trix-toolbar .trix-button.trix-active {
        background-color: rgb(59 130 246); /* blue-500 */
        color: white;
    }
    .dark trix-toolbar .trix-button-group {
        border-color: rgb(51 65 85);
    }
    trix-editor {
        min-height: 100px;
        border-radius: 0.5rem;
    }
    trix-toolbar {
        border-radius: 0.5rem 0.5rem 0 0;
    }
</style>
@endpush

@section('body')
<div class="container mx-auto px-4 py-8 max-w-5xl" x-data="statusMessagesEditor()">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('notices.settings.index') }}" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Status Messages</h1>
        </div>
        <p class="text-slate-600 dark:text-slate-400 text-sm">Configure messages shown in the notification viewer for each status type. Messages support HTML formatting.</p>
    </div>

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50 rounded-lg p-4 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-dcpl-blue dark:text-sky-300 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-sm text-blue-900 dark:text-blue-200">
                <p class="font-semibold mb-1">Rich Text Editor</p>
                <p class="text-blue-800 dark:text-blue-300">Use the editor to format messages with <strong>bold</strong>, <em>italic</em>, lists, and links. HTML is stored and rendered in the interface.</p>
            </div>
        </div>
    </div>

    <!-- Section Tabs -->
    <div class="flex gap-2 mb-6 border-b border-slate-200 dark:border-slate-700">
        <button
            @click="activeSection = 'delivered'"
            :class="activeSection === 'delivered' ? 'border-green-500 text-green-600 dark:text-green-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors flex items-center gap-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Delivered
            <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-1.5 py-0.5 rounded">{{ count($delivered_messages) }}</span>
        </button>
        <button
            @click="activeSection = 'pending'"
            :class="activeSection === 'pending' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors flex items-center gap-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Pending
            <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 px-1.5 py-0.5 rounded">{{ count($pending_messages) }}</span>
        </button>
        <button
            @click="activeSection = 'failed'"
            :class="activeSection === 'failed' ? 'border-red-500 text-red-600 dark:text-red-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors flex items-center gap-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            Failed
            <span class="text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 px-1.5 py-0.5 rounded">{{ count($failed_messages) }}</span>
        </button>
    </div>

    <!-- Delivered Messages Section -->
    <div x-show="activeSection === 'delivered'" x-cloak>
        <div class="mb-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">Messages shown when a notification has been successfully delivered.</p>
        </div>
        <div class="space-y-4">
            @foreach($delivered_messages as $index => $msg)
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                <div class="px-4 py-3 bg-green-50 dark:bg-green-900/20 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div>
                            <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $msg['code'] }}</div>
                            <input
                                type="text"
                                x-model="deliveredMessages[{{ $index }}].label"
                                placeholder="Label"
                                class="mt-1 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-green-500"
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="saveMessage('delivered', {{ $index }})"
                        :disabled="saving === 'delivered-{{ $index }}'"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-green-600 dark:bg-green-500 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-600 disabled:opacity-50 transition-colors"
                    >
                        <svg x-show="saving === 'delivered-{{ $index }}'" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg x-show="saving !== 'delivered-{{ $index }}'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="saving === 'delivered-{{ $index }}' ? 'Saving...' : 'Save'"></span>
                    </button>
                </div>
                <div class="p-4">
                    <div class="text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">Message:</div>
                    <div x-data="{ inputId: 'delivered-message-{{ $index }}' }">
                        <input 
                            :id="inputId" 
                            type="hidden" 
                            x-model="deliveredMessages[{{ $index }}].message"
                        >
                        <trix-editor 
                            :input="inputId"
                            x-init="$el.addEventListener('trix-change', () => deliveredMessages[{{ $index }}].message = $el.inputElement?.value || '')"
                            class="trix-content bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg"
                        ></trix-editor>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Pending Messages Section -->
    <div x-show="activeSection === 'pending'" x-cloak>
        <div class="mb-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">Messages shown when a notification is still being processed.</p>
        </div>
        <div class="space-y-4">
            @foreach($pending_messages as $index => $msg)
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                <div class="px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div>
                            <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $msg['code'] }}</div>
                            <input
                                type="text"
                                x-model="pendingMessages[{{ $index }}].label"
                                placeholder="Label"
                                class="mt-1 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500"
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="saveMessage('pending', {{ $index }})"
                        :disabled="saving === 'pending-{{ $index }}'"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-amber-600 dark:bg-amber-500 text-white rounded-lg hover:bg-amber-700 dark:hover:bg-amber-600 disabled:opacity-50 transition-colors"
                    >
                        <svg x-show="saving === 'pending-{{ $index }}'" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg x-show="saving !== 'pending-{{ $index }}'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="saving === 'pending-{{ $index }}' ? 'Saving...' : 'Save'"></span>
                    </button>
                </div>
                <div class="p-4">
                    <div class="text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">Message:</div>
                    <div x-data="{ inputId: 'pending-message-{{ $index }}' }">
                        <input 
                            :id="inputId" 
                            type="hidden" 
                            x-model="pendingMessages[{{ $index }}].message"
                        >
                        <trix-editor 
                            :input="inputId"
                            x-init="$el.addEventListener('trix-change', () => pendingMessages[{{ $index }}].message = $el.inputElement?.value || '')"
                            class="trix-content bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg"
                        ></trix-editor>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Failed Messages Section -->
    <div x-show="activeSection === 'failed'" x-cloak>
        <div class="mb-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">Messages explaining specific failure types and providing guidance for staff. Each includes a "What Happened" message and an "Action" for resolution.</p>
        </div>
        <div class="space-y-4">
            @foreach($failed_messages as $index => $msg)
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div>
                            <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $msg['code'] }}</div>
                            <input
                                type="text"
                                x-model="failedMessages[{{ $index }}].label"
                                placeholder="Label"
                                class="mt-1 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-red-500"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            @if($msg['source'])
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $msg['source'] === 'shoutbomb' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' : ($msg['source'] === 'notification_log' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300') }}">{{ $msg['source'] }}</span>
                            @endif
                            @if($msg['is_contact_issue'])
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">contact issue</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <input
                                type="checkbox"
                                x-model="failedMessages[{{ $index }}].is_enabled"
                                class="rounded border-slate-300 dark:border-slate-600 text-red-600 focus:ring-red-500"
                            />
                            <span>Enabled</span>
                        </label>
                        <button
                            type="button"
                            @click="saveMessage('failed', {{ $index }})"
                            :disabled="saving === 'failed-{{ $index }}'"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-red-600 dark:bg-red-500 text-white rounded-lg hover:bg-red-700 dark:hover:bg-red-600 disabled:opacity-50 transition-colors"
                        >
                            <svg x-show="saving === 'failed-{{ $index }}'" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg x-show="saving !== 'failed-{{ $index }}'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span x-text="saving === 'failed-{{ $index }}' ? 'Saving...' : 'Save'"></span>
                        </button>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <!-- Message (What Happened) -->
                    <div>
                        <div class="text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">What Happened:</div>
                        <div x-data="{ inputId: 'failed-message-{{ $index }}' }">
                            <input 
                                :id="inputId" 
                                type="hidden" 
                                x-model="failedMessages[{{ $index }}].message"
                            >
                            <trix-editor 
                                :input="inputId"
                                x-init="$el.addEventListener('trix-change', () => failedMessages[{{ $index }}].message = $el.inputElement?.value || '')"
                                class="trix-content bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg"
                            ></trix-editor>
                        </div>
                    </div>
                    <!-- Action (Resolution Steps) -->
                    <div>
                        <div class="text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">Action (resolution steps for staff):</div>
                        <div x-data="{ inputId: 'failed-action-{{ $index }}' }">
                            <input 
                                :id="inputId" 
                                type="hidden" 
                                x-model="failedMessages[{{ $index }}].action"
                            >
                            <trix-editor 
                                :input="inputId"
                                x-init="$el.addEventListener('trix-change', () => failedMessages[{{ $index }}].action = $el.inputElement?.value || '')"
                                class="trix-content bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg"
                            ></trix-editor>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
function statusMessagesEditor() {
    return {
        activeSection: 'delivered',
        deliveredMessages: @js($delivered_messages),
        pendingMessages: @js($pending_messages),
        failedMessages: @js($failed_messages),
        saving: null,

        async saveMessage(status, index) {
            // Get the message data based on status
            let messages, msg;
            if (status === 'delivered') {
                messages = this.deliveredMessages;
            } else if (status === 'pending') {
                messages = this.pendingMessages;
            } else {
                messages = this.failedMessages;
            }
            msg = messages[index];
            
            // Get current values from Trix editors
            const messageInput = document.getElementById(`${status}-message-${index}`);
            const actionInput = document.getElementById(`${status}-action-${index}`);
            
            if (messageInput) {
                msg.message = messageInput.value;
            }
            if (actionInput) {
                msg.action = actionInput.value;
            }
            
            this.saving = `${status}-${index}`;
            
            try {
                const response = await fetch('{{ route('notices.settings.messages.status-message.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        id: msg.id,
                        label: msg.label,
                        message: msg.message,
                        action: msg.action || null,
                        is_enabled: msg.is_enabled ?? true,
                    }),
                });
                
                const data = await response.json();
                if (data.success) {
                    console.log('Status message saved', data.status_message);
                }
            } catch (e) {
                console.error('Failed to save status message', e);
            } finally {
                this.saving = null;
            }
        }
    };
}

// Initialize Trix editors with existing content
document.addEventListener('trix-initialize', function(event) {
    const editor = event.target;
    const input = editor.inputElement;
    if (input && input.value) {
        editor.editor.loadHTML(input.value);
    }
});
</script>
@endpush
@endsection
