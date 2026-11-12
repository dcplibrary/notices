<div
    x-data="{
        open: false,
        notificationId: null,
        notification: null,
        loading: false,

        async openModal(id) {
            this.notificationId = id;
            this.open = true;
            this.loading = true;

            try {
                const response = await fetch(`{{ url('notices/notices-viewer') }}/${id}`);
                const data = await response.json();
                this.notification = data;
            } catch (error) {
                console.error('Failed to load notification:', error);
            } finally {
                this.loading = false;
            }
        },

        closeModal() {
            this.open = false;
            this.notification = null;
            this.notificationId = null;
        },

        getStatusColor(status) {
            switch (status) {
                case 'delivered': return 'text-green-600 dark:text-green-400';
                case 'failed': return 'text-red-600 dark:text-red-400';
                case 'pending': return 'text-amber-600 dark:text-amber-400';
                default: return 'text-slate-600 dark:text-slate-400';
            }
        },

        getStatusBg(status) {
            switch (status) {
                case 'delivered': return 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800';
                case 'failed': return 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800';
                case 'pending': return 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800';
                default: return 'bg-slate-50 dark:bg-slate-900/20 border-slate-200 dark:border-slate-800';
            }
        },

        getStatusIcon(status) {
            switch (status) {
                case 'delivered': return 'M5 13l4 4L19 7';
                case 'failed': return 'M6 18L18 6M6 6l12 12';
                case 'pending': return 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
                default: return 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
            }
        }
    }"
    @keydown.escape.window="if (open) closeModal()"
    @user-portal-detail-open.window="openModal($event.detail.id)"
>
    <!-- Modal Backdrop -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/50 z-40"
        @click="closeModal()"
        style="display: none;"
    ></div>

    <!-- Modal Content -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="flex items-start justify-center min-h-screen px-4 pt-4 pb-20">
            <div
                @click.stop
                class="relative bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-2xl w-full mt-8 mb-8"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        Notification Details
                    </h3>
                    <button
                        @click="closeModal()"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Loading State -->
                <div x-show="loading" class="px-6 py-12 text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 dark:border-slate-700 border-t-dcpl-blue"></div>
                    <p class="mt-4 text-slate-600 dark:text-slate-400">Loading notification details...</p>
                </div>

                <!-- Content -->
                <div x-show="!loading && notification" class="px-6 py-6">
                    <!-- Status Banner -->
                    <div 
                        :class="getStatusBg(notification?.status)"
                        class="rounded-lg border p-4 mb-6"
                    >
                        <div class="flex items-start gap-3">
                            <div :class="getStatusColor(notification?.status)" class="mt-0.5">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getStatusIcon(notification?.status)"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <!-- Show status message label from database -->
                                <h4 :class="getStatusColor(notification?.status)" class="font-semibold text-lg">
                                    <span x-show="notification?.status_message" x-text="notification?.status_message?.label"></span>
                                    <span x-show="!notification?.status_message" x-text="notification?.status" class="capitalize"></span>
                                </h4>
                                
                                <!-- Status message from database -->
                                <div class="text-slate-700 dark:text-slate-300 mt-1 prose prose-sm dark:prose-invert max-w-none" x-html="notification?.status_message?.message || getStatusFallbackMessage(notification?.status)"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Info -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <div class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">Patron</div>
                            <div class="text-slate-900 dark:text-white font-medium" x-text="notification?.patron_name || '—'"></div>
                            <div class="text-sm text-slate-600 dark:text-slate-400 font-mono" x-text="notification?.patron_barcode"></div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">Type</div>
                            <div class="text-slate-900 dark:text-white" x-text="notification?.notification_type"></div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">Item</div>
                            <div class="text-slate-900 dark:text-white" x-text="notification?.item_title || '—'"></div>
                            <div class="text-sm text-slate-600 dark:text-slate-400 font-mono" x-text="notification?.item_barcode"></div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">Contact Info</div>
                            <div class="text-slate-900 dark:text-white" x-text="notification?.contact_info || '—'"></div>
                            <div class="text-sm text-slate-600 dark:text-slate-400" x-text="notification?.channel"></div>
                        </div>
                    </div>

                    <!-- What to do section - shown for failed with action from database -->
                    <template x-if="notification?.status === 'failed' && notification?.status_message?.action">
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-dcpl-blue dark:text-sky-300 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="flex-1">
                                    <h5 class="font-medium text-blue-900 dark:text-blue-200 mb-2">What to do</h5>
                                    <div class="text-sm text-blue-800 dark:text-blue-300 prose prose-sm dark:prose-invert max-w-none" x-html="notification?.status_message?.action"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 rounded-b-lg">
                    <button
                        @click="closeModal()"
                        class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Fallback messages when no status_message is found in database
function getStatusFallbackMessage(status) {
    switch (status) {
        case 'delivered': return '<p>This notification was successfully delivered.</p>';
        case 'pending': return '<p>This notification is still being processed.</p>';
        case 'failed': return '<p>This notification could not be delivered.</p>';
        default: return '<p>Status unknown.</p>';
    }
}
</script>
@endpush
