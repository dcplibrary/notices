<div
    x-data="{
        open: false,
        issueId: null,
        issue: null,
        notification: null,
        loading: false,
        loadingNotification: false,
        activeTab: 'details',
        notificationTab: 'details',

        async openModal(id) {
            this.issueId = id;
            this.open = true;
            this.loading = true;
            this.activeTab = 'details';
            this.notification = null;

            try {
                const response = await fetch(`/notices/api/issues/${id}`);
                const data = await response.json();
                console.log('Loaded issue:', data);
                this.issue = data;
            } catch (error) {
                console.error('Failed to load issue:', error);
                alert('Failed to load issue details. Check console for errors.');
            } finally {
                this.loading = false;
            }
        },

        async loadNotification() {
            if (!this.issue?.notification_id || this.notification) return;

            this.loadingNotification = true;
            try {
                const response = await fetch(`/notices/api/notifications/${this.issue.notification_id}`);
                const data = await response.json();
                console.log('Loaded notification:', data);
                this.notification = data;
            } catch (error) {
                console.error('Failed to load notification:', error);
                alert('Failed to load notification details. Check console for errors.');
            } finally {
                this.loadingNotification = false;
            }
        },

        closeModal() {
            this.open = false;
            this.issue = null;
            this.notification = null;
            this.issueId = null;
            this.notificationTab = 'details';
        },

        jumpToLatest() {
            this.$nextTick(() => {
                const el = this.$refs.scrollBody;
                if (!el) return;
                el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            });
        }
    }"
    @keydown.escape.window="if (open) closeModal()"
    @issue-detail-open.window="openModal($event.detail.id)"
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
                class="relative bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-4xl w-full mt-8 mb-8"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        Delivery Issue Details
                        <span x-show="issue" class="text-slate-500 dark:text-slate-400 font-mono text-sm ml-2">#<span x-text="issue?.id"></span></span>
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
                    <p class="mt-4 text-slate-600 dark:text-slate-400">Loading issue details...</p>
                </div>

                <!-- Content -->
                <div x-show="!loading && issue">
                    <!-- Tabs -->
                    <div class="border-b border-slate-200 dark:border-slate-700">
                        <nav class="flex px-6 -mb-px space-x-8" aria-label="Tabs">
                            <button
                                @click="activeTab = 'details'"
                                :class="activeTab === 'details' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600'"
                                class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                            >
                                Details
                            </button>
                            <button
                                @click="activeTab = 'notification'"
                                :class="activeTab === 'notification' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600'"
                                class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                            >
                                Related Notification
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="px-6 py-6 max-h-[60vh] overflow-y-auto" x-ref="scrollBody">
                        <!-- Details Tab -->
                        <div x-show="activeTab === 'details'" class="space-y-4">
                            <!-- Status Section -->
                            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                                <div class="flex items-center gap-3">
                                    <span
                                        :class="{
                                            'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400': issue?.resolved_at,
                                            'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400': !issue?.resolved_at
                                        }"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium"
                                    >
                                        <span class="w-2 h-2 rounded-full" :class="issue?.resolved_at ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <span x-text="issue?.resolved_at ? 'Resolved' : 'Active'"></span>
                                    </span>
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300"
                                        x-text="issue?.issue_type?.replace(/_/g, ' ').toUpperCase()"
                                    ></span>
                                </div>
                            </div>

                            <!-- Patron Information -->
                            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Patron Information</div>
                                <div class="flex items-start gap-6 text-xs">
                                    <div>
                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Barcode</div>
                                        <div class="text-slate-900 dark:text-white font-mono" x-text="issue?.patron_barcode"></div>
                                    </div>
                                    <div x-show="issue?.patron_name">
                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Name</div>
                                        <div class="text-slate-900 dark:text-white" x-text="issue?.patron_name"></div>
                                    </div>
                                </div>
                                <div class="mt-2" x-show="issue?.patron_id">
                                    <a
                                        :href="`https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/${issue?.patron_id}/record`"
                                        target="_blank"
                                        class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                    >
                                        View Patron in Polaris
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>

                            <!-- Issue Information -->
                            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Issue Information</div>
                                <div class="flex items-start gap-6 text-xs">
                                    <div>
                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Detected</div>
                                        <div class="text-slate-900 dark:text-white" x-text="issue?.detected_at"></div>
                                    </div>
                                    <div x-show="issue?.delivery_method">
                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Delivery Method</div>
                                        <div class="text-slate-900 dark:text-white" x-text="issue?.delivery_method"></div>
                                    </div>
                                </div>
                                <div x-show="issue?.failed_delivery_string" class="mt-2">
                                    <div class="text-slate-500 dark:text-slate-400 mb-0.5 text-xs">Failed Contact</div>
                                    <div class="text-sm text-red-600 dark:text-red-400 font-mono" x-text="issue?.failed_delivery_string"></div>
                                </div>
                                <div x-show="issue?.issue_details" class="mt-2">
                                    <div class="text-slate-500 dark:text-slate-400 mb-0.5 text-xs">Details</div>
                                    <div class="text-sm text-slate-900 dark:text-white" x-text="issue?.issue_details"></div>
                                </div>
        <!-- LX Starter failures: clarify these are system-level, not patron fault -->
        <div
            x-show="issue?.issue_type === 'lx_starter_failure'"
            class="mt-3 text-xs text-orange-800 dark:text-orange-300"
        >
            <x-notices::status-message key="lx_starter_failure" class="text-xs text-orange-800 dark:text-orange-300" />
        </div>
                            </div>

                            <!-- Resolution Information -->
                            <div x-show="issue?.resolved_at" class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 border border-green-200 dark:border-green-800">
                                <div class="text-xs font-semibold text-green-900 dark:text-green-300 mb-2">Resolution</div>
                                <div class="flex items-start gap-6 text-xs">
                                    <div>
                                        <div class="text-green-700 dark:text-green-400 mb-0.5">Resolved At</div>
                                        <div class="text-green-900 dark:text-green-200" x-text="issue?.resolved_at"></div>
                                    </div>
                                    <div x-show="issue?.resolved_by_name">
                                        <div class="text-green-700 dark:text-green-400 mb-0.5">Resolved By</div>
                                        <div class="text-green-900 dark:text-green-200" x-text="issue?.resolved_by_name"></div>
                                    </div>
                                </div>
                                <div x-show="issue?.resolution_notes" class="mt-2">
                                    <div class="text-green-700 dark:text-green-400 mb-0.5 text-xs">Resolution Notes</div>
                                    <div class="text-sm text-green-900 dark:text-green-200" x-text="issue?.resolution_notes"></div>
                                </div>
                            </div>

                            <!-- Sources -->
                            <div x-show="issue?.sources">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Sources</h4>
                                <div class="space-y-3">
                                    <!-- Export File (if linked to notification) -->
                                    <div x-show="issue?.sources?.export_file" class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Export File</div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-3 text-xs text-slate-600 dark:text-slate-400">
                                                <span x-text="issue?.sources?.export_file?.export_date"></span>
                                                <span class="text-slate-400 dark:text-slate-600">•</span>
                                                <span class="text-dcpl-blue dark:text-sky-300 font-medium">Notification Created</span>
                                            </div>
                                            <div class="text-xs text-slate-700 dark:text-slate-300 font-mono bg-white dark:bg-slate-800 px-2 py-1.5 rounded border border-slate-200 dark:border-slate-700 overflow-x-auto whitespace-nowrap" x-text="issue?.sources?.export_file?.filename"></div>
                                            <div x-show="issue?.sources?.export_file?.batch_identifier" class="text-xs text-slate-600 dark:text-slate-400">
                                                Batch: <span class="font-mono" x-text="issue?.sources?.export_file?.batch_identifier"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detection Information -->
                                    <div x-show="issue?.sources?.detected_during" class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Issue Detection</div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-3 text-xs text-slate-600 dark:text-slate-400">
                                                <span x-text="issue?.sources?.detected_during?.timestamp"></span>
                                                <span class="text-slate-400 dark:text-slate-600">•</span>
                                                <span class="text-orange-600 dark:text-orange-400 font-medium">Issue Detected</span>
                                            </div>
                                            <div class="text-xs text-slate-700 dark:text-slate-300">
                                                Detected during import batch processing
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Failed Contact (for failed_delivery_string issues) -->
                                <div x-show="issue?.sources?.failed_contact" class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 border border-red-200 dark:border-red-800">
                                        <div class="text-xs font-semibold text-red-900 dark:text-red-300 mb-2">Failed Contact Information</div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-3 text-xs text-red-700 dark:text-red-400">
                                                <span x-text="issue?.sources?.failed_contact?.first_detected"></span>
                                                <span class="text-red-400 dark:text-red-600">•</span>
                                                <span class="font-medium">First Detected</span>
                                            </div>
                                            <div class="text-sm text-red-900 dark:text-red-200 font-mono bg-white dark:bg-red-950/30 px-2 py-1.5 rounded border border-red-200 dark:border-red-800" x-text="issue?.sources?.failed_contact?.delivery_string"></div>
                                            <div class="text-xs text-red-700 dark:text-red-300">
                                                <x-notices::status-message key="failed_contact_string" class="text-xs text-red-700 dark:text-red-300" />
                                            </div>
                                            <div
                                                x-show="issue?.issue_type === 'failed_delivery_string' && issue?.notification_delivery_option_id === 8"
                                                class="mt-1"
                                            >
                                                <x-notices::status-message key="invalid_phone_sms" class="text-xs" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Related Notification Tab -->
                        <div x-show="activeTab === 'notification'" x-init="$watch('activeTab', value => { if (value === 'notification') loadNotification() })">
                            <div x-show="issue?.notification_id">
                                <div class="flex items-center justify-end mb-2">
                                    <button
                                        type="button"
                                        x-show="notificationTab === 'timeline'"
                                        @click="jumpToLatest()"
                                        class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline"
                                    >
                                        Jump to latest
                                    </button>
                                </div>
                                <!-- Loading State -->
                                <div x-show="loadingNotification" class="text-center py-12">
                                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 dark:border-slate-700 border-t-dcpl-blue"></div>
                                    <p class="mt-4 text-slate-600 dark:text-slate-400">Loading notification details...</p>
                                </div>

                                <!-- Notification Details -->
                                <div x-show="!loadingNotification && notification">
                                    <!-- Notification Sub-tabs -->
                                    <div class="border-b border-slate-200 dark:border-slate-700 mb-4">
                                        <nav class="flex -mb-px space-x-6" aria-label="Notification Tabs">
                                            <button
                                                @click="notificationTab = 'details'"
                                                :class="notificationTab === 'details' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600'"
                                                class="py-3 px-1 border-b-2 font-medium text-xs transition-colors"
                                            >
                                                Details
                                            </button>
                                            <button
                                                @click="notificationTab = 'raw'"
                                                :class="notificationTab === 'raw' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600'"
                                                class="py-3 px-1 border-b-2 font-medium text-xs transition-colors"
                                            >
                                                Raw Data
                                            </button>
                                            <button
                                                @click="notificationTab = 'timeline'"
                                                :class="notificationTab === 'timeline' ? 'border-blue-500 text-dcpl-blue dark:text-sky-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600'"
                                                class="py-3 px-1 border-b-2 font-medium text-xs transition-colors"
                                            >
                                                Timeline
                                            </button>
                                        </nav>
                                    </div>

                                    <!-- Notification Tab Content -->
                                    <div class="space-y-4">
                                        <!-- Details Sub-tab -->
                                        <div x-show="notificationTab === 'details'" class="space-y-4">
                                            <!-- Patron Information -->
                                            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Patron Information</div>
                                                <div class="flex items-start gap-6 text-xs">
                                                    <div>
                                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Barcode</div>
                                                        <div class="text-slate-900 dark:text-white font-mono" x-text="notification?.patron_barcode"></div>
                                                    </div>
                                                    <div>
                                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Name</div>
                                                        <div class="text-slate-900 dark:text-white" x-text="notification?.patron_name || '—'"></div>
                                                    </div>
                                                </div>
                                                <div class="mt-2" x-show="notification?.patron_id">
                                                    <a
                                                        :href="`https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/${notification?.patron_id}/record`"
                                                        target="_blank"
                                                        class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                                    >
                                                        View Patron in Polaris
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- Notification Information -->
                                            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Notification Information</div>
                                                <div class="flex items-start gap-6 text-xs">
                                                    <div>
                                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Type</div>
                                                        <div class="text-slate-900 dark:text-white" x-text="notification?.notification_type"></div>
                                                    </div>
                                                    <div>
                                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Delivery Method</div>
                                                        <div class="text-slate-900 dark:text-white" x-text="notification?.delivery_option?.display_label || 'Unknown'"></div>
                                                    </div>
                                                    <div>
                                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Status</div>
                                                        <div class="text-sm">
                                                            <span
                                                                :class="{
                                                                    'text-green-600 dark:text-green-400': notification?.status === 'delivered',
                                                                    'text-red-600 dark:text-red-400': notification?.status === 'failed',
                                                                    'text-yellow-600 dark:text-yellow-400': notification?.status === 'pending'
                                                                }"
                                                                x-text="notification?.status"
                                                            ></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div x-show="notification?.failure_reason" class="mt-2">
                                                    <div class="text-slate-500 dark:text-slate-400 mb-0.5 text-xs">Failure Reason</div>
                                                    <div class="text-sm text-red-600 dark:text-red-400" x-text="notification?.failure_reason"></div>
                                                </div>
                                            </div>

                                            <!-- Item Information -->
                                            <div x-show="notification?.item_title || notification?.item_barcode" class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Item Information</div>
                                                <div class="flex items-start gap-6 text-xs">
                                                    <div x-show="notification?.item_barcode">
                                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Item Barcode</div>
                                                        <div class="text-slate-900 dark:text-white font-mono" x-text="notification?.item_barcode"></div>
                                                    </div>
                                                    <div x-show="notification?.item_title" class="flex-1">
                                                        <div class="text-slate-500 dark:text-slate-400 mb-0.5">Title</div>
                                                        <div class="text-slate-900 dark:text-white" x-text="notification?.item_title"></div>
                                                    </div>
                                                </div>
                                                <div class="mt-2" x-show="notification?.item_record_id">
                                                    <a
                                                        :href="`https://catalog.dcplibrary.org/leapwebapp/staff/default#itemrecords/${notification?.item_record_id}`"
                                                        target="_blank"
                                                        class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                                    >
                                                        View Item Record in Polaris
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Raw Data Sub-tab -->
                                        <div x-show="notificationTab === 'raw'">
                                            <pre class="bg-slate-900 text-slate-100 p-4 rounded-lg overflow-x-auto text-xs" x-text="JSON.stringify(notification, null, 2)"></pre>
                                        </div>

                                        <!-- Timeline Sub-tab -->
                                        <div x-show="notificationTab === 'timeline'" class="space-y-4">
                                            <div class="flex items-start gap-4" x-show="notification?.generated_at">
                                                <div class="flex-shrink-0 w-32 text-xs text-slate-500 dark:text-slate-400" x-text="notification?.generated_at"></div>
                                                <div class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full bg-blue-500"></div>
                                                <div class="flex-1">
                                                    <div class="text-sm font-medium text-slate-900 dark:text-white">Generated</div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">Notification created in Polaris</div>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-4" x-show="notification?.first_exported_at">
                                                <div class="flex-shrink-0 w-32 text-xs text-slate-500 dark:text-slate-400" x-text="notification?.first_exported_at"></div>
                                                <div class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full bg-purple-500"></div>
                                                <div class="flex-1">
                                                    <div class="text-sm font-medium text-slate-900 dark:text-white">First Exported</div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                                        <span>First appearance in export file</span>
                                                        <span x-show="notification?.source_filename" class="block mt-1 font-mono text-dcpl-blue dark:text-sky-300" x-text="notification?.source_filename"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-4" x-show="notification?.last_exported_at && notification?.last_exported_at !== notification?.first_exported_at">
                                                <div class="flex-shrink-0 w-32 text-xs text-slate-500 dark:text-slate-400" x-text="notification?.last_exported_at"></div>
                                                <div class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full bg-purple-300"></div>
                                                <div class="flex-1">
                                                    <div class="text-sm font-medium text-slate-900 dark:text-white">Last Exported</div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                                        <span>Last appearance in export file</span>
                                                        <span x-show="notification?.import_batch_identifier" class="block mt-1 font-mono text-dcpl-blue dark:text-sky-300" x-text="notification?.import_batch_identifier"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-4" x-show="notification?.delivered_at">
                                                <div class="flex-shrink-0 w-32 text-xs text-slate-500 dark:text-slate-400" x-text="notification?.delivered_at"></div>
                                                <div class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full bg-green-500"></div>
                                                <div class="flex-1">
                                                    <div class="text-sm font-medium text-slate-900 dark:text-white">Delivered</div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">Successfully delivered to patron</div>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-4" x-show="notification?.failed_at">
                                                <div class="flex-shrink-0 w-32 text-xs text-slate-500 dark:text-slate-400" x-text="notification?.failed_at"></div>
                                                <div class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full bg-red-500"></div>
                                                <div class="flex-1">
                                                    <div class="text-sm font-medium text-slate-900 dark:text-white">Failed</div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400" x-text="notification?.failure_reason || 'Delivery failed'"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div x-show="!issue?.notification_id" class="text-center py-8">
                                <svg class="w-12 h-12 mx-auto text-slate-400 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="text-sm text-slate-600 dark:text-slate-400">No notification associated with this issue</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
