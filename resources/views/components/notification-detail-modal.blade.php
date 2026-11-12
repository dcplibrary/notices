<div
    x-data="{
        open: false,
        notificationId: null,
        notification: null,
        loading: false,
        activityEntries: [],

        async openModal(id) {
            this.notificationId = id;
            this.open = true;
            this.loading = true;

            try {
                const response = await fetch(`/notices/api/notifications/${id}`);
                const data = await response.json();
                console.log('Loaded notification:', data);
                this.notification = data;
                this.activityEntries = Array.isArray(data.activity_log) ? data.activity_log : [];
            } catch (error) {
                console.error('Failed to load notification:', error);
                alert('Failed to load notification details. Check console for errors.');
            } finally {
                this.loading = false;
            }
        },

        closeModal() {
            this.open = false;
            this.notification = null;
            this.notificationId = null;
            this.activityEntries = [];
        },

        jumpToLatest() {
            this.$nextTick(() => {
                const el = this.$refs.scrollBody;
                if (!el) return;
                el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            });
        },

        copyLink() {
            const url = `${window.location.origin}/notices/notifications/${this.notificationId}`;
            navigator.clipboard.writeText(url);
        },

        getIssueDescription(issueType) {
            const descriptions = {
                'polaris_export_failure': 'Notification exported from Polaris but never sent by LX Starter',
                'lx_starter_failure': 'Notification never uploaded to LX Starter',
                'failed_delivery_string': 'Patron uses a delivery method that has failed',
                'null_preference': 'Patron has no delivery preference configured'
            };
            return descriptions[issueType] || issueType.replace(/_/g, ' ');
        },

        formatActivityDescription(entry) {
            if (!entry || !entry.description) return '';

            const desc = entry.description;

            // Look for a JSON object wrapped in parentheses, e.g.
            // Example: Notification updated from ({...}) export file
            const parenMatch = desc.match(/^(.*?)(\({.*}\))(.*)$/);
            let jsonStr = null;
            let prefix = '';
            let suffix = '';

            if (parenMatch) {
                prefix = parenMatch[1].trimEnd();
                jsonStr = parenMatch[2].replace(/^\(/, '').replace(/\)$/, '');
                suffix = parenMatch[3] || '';
            } else {
                // Fallback: try to find the first {...} block in the string
                const firstBrace = desc.indexOf('{');
                const lastBrace = desc.lastIndexOf('}');
                if (firstBrace !== -1 && lastBrace > firstBrace) {
                    prefix = desc.slice(0, firstBrace).trimEnd();
                    jsonStr = desc.slice(firstBrace, lastBrace + 1);
                    suffix = desc.slice(lastBrace + 1) || '';
                } else {
                    return desc;
                }
            }

            try {
                const parsed = JSON.parse(jsonStr);
                const lines = Object.entries(parsed).map(([key, value]) => {
                    if (value === null || value === undefined) return `${key}: —`;
                    if (typeof value === 'string') return `${key}: ${value}`;
                    return `${key}: ${JSON.stringify(value)}`;
                });

                const prettyJson = lines.join('\n  ');

                const base = prefix || 'Notification updated from';
                const tail = suffix.trim();

                return tail
                    ? `${base}:\n  ${prettyJson}\n${tail}`
                    : `${base}:\n  ${prettyJson}`;
            } catch (error) {
                // If parsing fails, fall back to the raw description
                return desc;
            }
        },

        formatFileAction(entry) {
            if (!entry) return 'file';
            const desc = (entry.description || '').toLowerCase();

            if (desc.includes('created')) {
                return 'created by';
            }

            if (desc.includes('updated')) {
                return 'updated by';
            }

            return 'file';
        },

        buildFileHref(entry) {
            if (!entry) {
                return '/notices/data/export-files';
            }

            // Summary report emails: link directly to the email detail view
            if (entry.file_type === 'summary_report_email' && entry.file_meta && entry.file_meta.ingested_email_id) {
                return '/notices/data/email-detail/' + entry.file_meta.ingested_email_id;
            }

            if (!entry.filename) {
                return '/notices/data/export-files';
            }

            const filename = entry.filename;

            // Special handling for Voice/SMS export files like
            // holds_2026-01-07_08-05-01.txt, renew_..., overdue_...
            const m = filename.match(/^(holds|renew|overdue)_\d{4}-\d{2}-\d{2}/);

            if (m) {
                const prefix = m[1];
                const batchKey = m[0]; // e.g. holds_2026-01-07

                let source = '';
                if (prefix === 'holds') {
                    source = 'ftp_holds';
                } else if (prefix === 'renew') {
                    source = 'ftp_renew';
                } else if (prefix === 'overdue') {
                    source = 'ftp_overdue';
                }

                const params = new URLSearchParams();
                params.set('search', batchKey);
                if (source) {
                    params.set('source', source);
                }

                return '/notices/data/export-files?' + params.toString();
            }

            // Fallback: generic search by filename
            return '/notices/data/export-files?search=' + encodeURIComponent(filename);
        }
    }"
    @keydown.escape.window="if (open) closeModal()"
    @notification-detail-open.window="openModal($event.detail.id)"
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
                        Notification Details
                        <span x-show="notification" class="text-slate-500 dark:text-slate-400 font-mono text-sm ml-2">#<span x-text="notification?.id"></span></span>
                    </h3>
                    <div class="flex items-center gap-2">
                        <button
                            @click="copyLink()"
                            class="px-3 py-1 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors"
                            title="Copy link"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                        <button
                            @click="closeModal()"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Loading State -->
                <div x-show="loading" class="px-6 py-12 text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 dark:border-slate-700 border-t-dcpl-blue"></div>
                    <p class="mt-4 text-slate-600 dark:text-slate-400">Loading notification details...</p>
                </div>

                <!-- Content -->
                <div x-show="!loading && notification">
                    <!-- Tabs -->
                    <div class="border-b border-slate-200 dark:border-slate-700">
                        <div class="flex px-6 -mb-px space-x-8" aria-label="Tabs">
                            <div class="py-4 px-1 border-b-2 border-dcpl-blue text-dcpl-blue dark:text-sky-300 font-medium text-sm">
                                Details
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="px-6 py-6 max-h-[60vh] overflow-y-auto" x-ref="scrollBody">
                        <div class="space-y-4">
                            <!-- Active Issues Alert (shown prominently if issues exist) -->
                            <div x-show="notification?.active_issues && notification.active_issues.length > 0" class="rounded-lg border-2 border-orange-300 dark:border-orange-700 bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 p-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 p-2 bg-orange-100 dark:bg-orange-900/40 rounded-lg">
                                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-bold text-orange-900 dark:text-orange-300 mb-2">Active Delivery Issue</h4>
                                        <template x-for="issue in (notification && notification.active_issues) ? notification.active_issues : []" :key="issue.id">
                                            <div class="mb-3 last:mb-0">
                                                <div class="flex items-start justify-between gap-2 mb-1">
                                                    <span class="text-xs font-semibold text-orange-700 dark:text-orange-300 uppercase tracking-wide" x-text="issue.issue_type.replace(/_/g, ' ')"></span>
                                                    <span class="text-xs text-slate-600 dark:text-slate-400" x-text="'Detected: ' + issue.detected_at"></span>
                                                </div>
                                                <p class="text-sm text-slate-700 dark:text-slate-300" x-text="issue.issue_details || getIssueDescription(issue.issue_type)"></p>
                                                <a
                                                    :href="`/notices/issues?issue_type=${issue.issue_type}`"
                                                    class="mt-2 text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                                >
                                                    View all <span x-text="issue.issue_type.replace(/_/g, ' ')"></span> issues
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- EMAIL NOTIFICATIONS: Custom Layout -->
                            <div x-show="notification?.delivery_option?.id === 2" class="space-y-4">
                                <!-- Status Section (standardized like non-email) -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4 border border-slate-200 dark:border-slate-700">
                                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Status</div>
                                    <div class="flex items-start justify-between gap-6 text-xs">
                                        <div>
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Current Status</div>
                                            <div class="text-sm">
                                                <span
                                                    :class="{
                                                        'text-red-600 dark:text-red-400 font-medium': (notification?.failure_reason && notification.failure_reason.includes('LX Starter communication error')) || (notification?.active_issues || []).some(i => i.issue_type === 'lx_starter_failure'),
                                                        'text-green-600 dark:text-green-400 font-medium': !((notification?.failure_reason && notification.failure_reason.includes('LX Starter communication error')) || (notification?.active_issues || []).some(i => i.issue_type === 'lx_starter_failure')) && notification?.status === 'delivered',
                                                        'text-yellow-600 dark:text-yellow-400 font-medium': notification?.status === 'pending'
                                                    }"
                                                    x-text="((notification?.failure_reason && notification.failure_reason.includes('LX Starter communication error')) || (notification?.active_issues || []).some(i => i.issue_type === 'lx_starter_failure')) ? 'failed' : (notification?.status || 'unknown')"
                                                ></span>
                                            </div>
                                        </div>
                                        <div class="flex-1 ml-8">
                                            <div class="text-slate-500 dark:text-slate-400 mb-1 text-xs">Failure Reason</div>
                                            <!-- LX Starter system failures use UI message -->
                                            <div
                                                x-show="(notification?.failure_reason && notification.failure_reason.includes('LX Starter communication error')) || (notification?.active_issues || []).some(i => i.issue_type === 'lx_starter_failure')"
                                                class="text-sm text-red-600 dark:text-red-400 mb-2"
                                            >
                                                <x-notices::status-message key="lx_starter_failure" class="text-sm text-red-600 dark:text-red-400" />
                                            </div>

@php
    $emailFailureKey = null;
    $statusId = $notification['sources']['notification_log']['status_id'] ?? null;
    $failureReason = $notification['failure_reason'] ?? null;

    // Polaris notification_log codes 13/14 are email failures.
    if ($statusId === 13) {
        // Typically "Email Failed - Invalid address"
        $emailFailureKey = 'email_invalid_address';
    } elseif ($statusId === 14) {
        // Generic email failed; if the text suggests a bounce, use bounce message
        if ($failureReason && (str_contains(strtolower($failureReason), 'bounce') || str_contains(strtolower($failureReason), 'bounced'))) {
            $emailFailureKey = 'email_bounce';
        } else {
            $emailFailureKey = 'email_delivery_failure';
        }
    }
@endphp

@unless($failureReason && str_contains($failureReason, 'LX Starter communication error'))
    @if($emailFailureKey)
        <x-notices::status-message key="{{ $emailFailureKey }}" class="text-sm text-red-600 dark:text-red-400 mb-2" />
    @elseif($failureReason)
        <div class="text-sm text-red-600 dark:text-red-400 mb-2">
            {{ $failureReason }}
        </div>
    @endif
@endunless

                                            <a
                                                x-show="notification?.patron_id"
                                                :href="`https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/${notification?.patron_id}/record`"
                                                target="_blank"
                                                class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                            >
                                                Edit Notification Method
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                </svg>
                                            </a>
                                            <div class="mt-2">
                                                <a
                                                    :href="`https://lx-na3.iiivega.com/davi/audience/patrons/${notification?.patron_barcode}`"
                                                    target="_blank"
                                                    class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                                >
                                                    View in LX Starter
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Patron Information -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4 border border-slate-200 dark:border-slate-700">
                                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Patron Information</div>
                                    <div class="flex items-start gap-6 text-xs">
                                        <div>
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Name</div>
                                            <div class="text-slate-900 dark:text-white">
                                                <span x-text="notification?.patron_last_name || ''"></span><span x-show="notification?.patron_last_name && notification?.patron_first_name">, </span><span x-text="notification?.patron_first_name || ''"></span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Barcode</div>
                                            <div class="text-slate-900 dark:text-white font-mono" x-text="notification?.patron_barcode"></div>
                                        </div>
                                        <div>
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Delivery Method</div>
                                            <div class="text-slate-900 dark:text-white" x-text="notification?.delivery_option?.display_label || 'Unknown'"></div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Email Address</div>
                                            <div class="text-slate-900 dark:text-white" x-text="notification?.patron_email || '—'"></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <a
                                            x-show="notification?.patron_id"
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
                                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4 border border-slate-200 dark:border-slate-700">
                                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Notification Information</div>
                                    <div class="flex items-start gap-6 text-xs">
                                        <div>
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Date</div>
                                            <div class="text-slate-900 dark:text-white" x-text="notification?.notice_sent_date || '—'"></div>
                                        </div>
                                        <div>
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Type</div>
                                            <div class="text-slate-900 dark:text-white" x-text="notification?.notification_type"></div>
                                        </div>
                                        <div>
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Item Barcode</div>
                                            <div class="text-slate-900 dark:text-white font-mono" x-text="notification?.item_barcode || '—'"></div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Title</div>
                                            <div class="text-slate-900 dark:text-white" x-text="notification?.item_title || '—'"></div>
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

                            <!-- NON-EMAIL NOTIFICATIONS: Compact Section Layout -->
                            <div x-show="notification?.delivery_option?.id !== 2" class="space-y-4">
                                <!-- Status Section -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4 border border-slate-200 dark:border-slate-700">
                                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Status</div>
                                    <div class="flex items-start gap-6 text-xs">
                                        <div>
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Current Status</div>
                                            <div class="text-sm">
                                                <span
                                                    :class="{
                                                        'text-green-600 dark:text-green-400 font-medium': notification?.status === 'delivered',
                                                        'text-red-600 dark:text-red-400 font-medium': notification?.status === 'failed',
                                                        'text-yellow-600 dark:text-yellow-400 font-medium': notification?.status === 'pending'
                                                    }"
                                                    x-text="notification?.status"
                                                ></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-show="notification?.failure_reason" class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                                        <div class="text-slate-500 dark:text-slate-400 mb-1 text-xs">Failure Reason</div>

                                        @php
                                            $failureType = $notification['sources']['shoutbomb_failure']['failure_type'] ?? null;
                                            $smsKey = match($failureType) {
                                                'Opted Out'            => 'sms_opt_out',
                                                'Invalid Phone Number' => 'sms_invalid_phone',
                                                'Landline'            => 'sms_landline',
                                                'Carrier Issue'       => 'sms_carrier_issue',
                                                default               => 'sms_delivery_failure',
                                            };
                                        @endphp

                                        <x-notices::status-message key="{{ $smsKey }}" class="text-sm text-red-600 dark:text-red-400 mb-2" />
                                        <a
                                            x-show="notification?.patron_id"
                                            :href="`https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/${notification?.patron_id}/record`"
                                            target="_blank"
                                            class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                        >
                                            Edit Notification Method
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </div>

                                <!-- Patron Information -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4 border border-slate-200 dark:border-slate-700">
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
                                    <div class="mt-2">
                                        <a
                                            x-show="notification?.patron_id"
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
                                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4 border border-slate-200 dark:border-slate-700">
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
                                    </div>
                                </div>

                                <!-- Item Information -->
                                <div x-show="notification?.item_title || notification?.item_barcode" class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4 border border-slate-200 dark:border-slate-700">
                                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Item Information</div>
                                    <div class="flex items-start gap-6 text-xs">
                                        <div x-show="notification?.item_title">
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Title</div>
                                            <div class="text-slate-900 dark:text-white" x-text="notification?.item_title"></div>
                                        </div>
                                        <div x-show="notification?.item_barcode">
                                            <div class="text-slate-500 dark:text-slate-400 mb-0.5">Item Barcode</div>
                                            <div class="text-slate-900 dark:text-white font-mono" x-text="notification?.item_barcode"></div>
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

                            <!-- Unified Sources section for all delivery methods -->
                            <div x-show="notification" class="mt-4">
                                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700 p-4">
                                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Sources</h4>

                                    <div class="rounded-md overflow-hidden">
                                        <table class="min-w-full text-xs">
                                        <thead class="bg-slate-100 dark:bg-slate-900/70">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Type</th>
                                                <th class="px-3 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Date</th>
                                                <th class="px-3 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Status</th>
                                                <th class="px-3 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Filename / Details</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-900/30">
                                            <!-- Notification Log (Mail & Email) -->
                                            <tr>
                                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100">Notification Log</td>
                                                <td class="px-3 py-2 font-mono text-slate-700 dark:text-slate-300" x-text="notification?.sources?.notification_log?.log_date || '—'"></td>
                                                <td class="px-3 py-2">
                                                    <span
                                                        x-show="notification?.sources?.notification_log"
                                                        :class="{
                                                            'text-green-600 dark:text-green-400 font-medium': [12, 15, 16].includes(notification?.sources?.notification_log?.status_id || 0),
                                                            'text-red-600 dark:text-red-400 font-medium': [13, 14].includes(notification?.sources?.notification_log?.status_id || 0),
                                                            'text-slate-600 dark:text-slate-300': ![12,13,14,15,16].includes(notification?.sources?.notification_log?.status_id || 0)
                                                        }"
                                                        x-text="notification?.sources?.notification_log?.status || 'Unknown'"
                                                    ></span>
                                                    <span x-show="!notification?.sources?.notification_log" class="text-slate-400 dark:text-slate-500">No notification log record</span>
                                                </td>
                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                                    <span x-show="notification?.sources?.notification_log?.status_id === 15">Mail printed in Polaris</span>
                                                    <span x-show="[12,16].includes(notification?.sources?.notification_log?.status_id || 0)">Email delivered according to notification log</span>
                                                    <span x-show="[13,14].includes(notification?.sources?.notification_log?.status_id || 0)">Email failed according to notification log</span>
                                                    <span x-show="!notification?.sources?.notification_log" class="text-slate-400 dark:text-slate-500">—</span>
                                                </td>
                                            </tr>

                                            <!-- Summary Report (Email/Mail) -->
                                            <tr>
                                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100">Summary Report</td>
                                                <td class="px-3 py-2 font-mono text-slate-700 dark:text-slate-300" x-text="notification?.sources?.summary_report?.received_at || '—'"></td>
                                                <td class="px-3 py-2">
                                                    <span
                                                        x-show="notification?.sources?.summary_report"
                                                        :class="{
                                                            'text-green-600 dark:text-green-400 font-medium': notification?.sources?.summary_report?.category === 'Success',
                                                            'text-red-600 dark:text-red-400 font-medium': notification?.sources?.summary_report?.category === 'Failed',
                                                            'text-slate-600 dark:text-slate-300': !notification?.sources?.summary_report?.category
                                                        }"
                                                        x-text="notification?.sources?.summary_report?.category || 'Unknown'"
                                                    ></span>
                                                    <span x-show="!notification?.sources?.summary_report" class="text-slate-400 dark:text-slate-500">No summary report record</span>
                                                </td>
                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                                    <div x-show="notification?.sources?.summary_report">
                                                        <div x-show="notification?.sources?.summary_report?.section_header" class="font-semibold" x-text="notification?.sources?.summary_report?.section_header"></div>
                                                        <div x-show="notification?.sources?.summary_report?.details_line" class="font-mono text-xs overflow-x-auto whitespace-nowrap" x-text="notification?.sources?.summary_report?.details_line"></div>
                                                        <div x-show="notification?.sources?.summary_report?.export_date" class="mt-1">
                                                            <a
                                                                :href="`/notices/list?status=${notification?.status}&delivery_option_id=2&notification_type=${encodeURIComponent(notification?.notification_type)}&date_from=${notification?.sources?.summary_report?.export_date}&date_to=${notification?.sources?.summary_report?.export_date}`"
                                                                class="inline-flex items-center gap-1 text-dcpl-blue dark:text-sky-300 hover:underline"
                                                            >
                                                                <span>View all <span x-text="notification?.sources?.summary_report?.category"></span> <span x-text="notification?.notification_type"></span> E-mail notices for <span x-text="notification?.sources?.summary_report?.export_date"></span></span>
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                                </svg>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <span x-show="!notification?.sources?.summary_report" class="text-slate-400 dark:text-slate-500">No matching summary report for this notification</span>
                                                </td>
                                            </tr>

                                            <!-- Export File (Voice/SMS + any notification with export info) -->
                                            <tr>
                                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100">Export File</td>
                                                <td class="px-3 py-2 font-mono text-slate-700 dark:text-slate-300" x-text="notification?.sources?.export_file?.export_date || notification?.first_exported_at || '—'"></td>
                                                <td class="px-3 py-2 text-slate-700 dark:text-slate-300">
                                                    <span x-show="notification?.sources?.export_file">Created</span>
                                                    <span x-show="!notification?.sources?.export_file" class="text-slate-400 dark:text-slate-500">No export file metadata</span>
                                                </td>
                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                                    <span class="font-mono text-xs" x-text="notification?.sources?.export_file?.filename || notification?.source_filename || '—'"></span>
                                                </td>
                                            </tr>

                                            <!-- Shoutbomb Failure (Voice/SMS) -->
                                            <tr>
                                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100">Shoutbomb Failure</td>
                                                <td class="px-3 py-2 font-mono text-slate-700 dark:text-slate-300" x-text="notification?.sources?.shoutbomb_failure?.received_at || '—'"></td>
                                                <td class="px-3 py-2">
                                                    <span
                                                        x-show="notification?.sources?.shoutbomb_failure"
                                                        :class="{
                                                            'text-red-600 dark:text-red-400 font-medium': true
                                                        }"
                                                        x-text="notification?.sources?.shoutbomb_failure?.failure_type || 'Delivery Failure'"
                                                    ></span>
                                                    <span x-show="!notification?.sources?.shoutbomb_failure && [3,8].includes(notification?.delivery_option?.id || 0) && notification?.status === 'delivered'" class="text-green-600 dark:text-green-400">No failure report (delivered)</span>
                                                    <span x-show="!notification?.sources?.shoutbomb_failure && [3,8].includes(notification?.delivery_option?.id || 0) && notification?.status !== 'delivered'" class="text-slate-400 dark:text-slate-500">No failure report found</span>
                                                    <span x-show="!notification?.sources?.shoutbomb_failure && ![3,8].includes(notification?.delivery_option?.id || 0)" class="text-slate-400 dark:text-slate-500">Not applicable</span>
                                                </td>
                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                                    <span class="font-mono text-xs" x-text="notification?.sources?.shoutbomb_failure?.details_line || notification?.failure_reason || '—'"></span>
                                                </td>
                                            </tr>
                                        </tbody>
                                        </table>
                                    </div>

                                    <!-- Channel-specific explanatory callouts below the table -->
                                    <div class="mt-3 space-y-2">
                                    <!-- Voice/SMS grace period / success-by-absence -->
                                    <div x-show="[3, 8].includes(notification?.delivery_option?.id) && notification?.status === 'pending'" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border border-blue-200 dark:border-blue-800">
                                        <div class="flex items-start gap-2">
                                            <svg class="w-4 h-4 text-dcpl-blue dark:text-sky-300 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div class="text-xs text-blue-900 dark:text-blue-200">
                                                <strong>24-Hour Grace Period:</strong> Voice/SMS notifications use "success-by-absence" logic. Status will update 24+ hours after export once failure reports have been processed.
                                            </div>
                                        </div>
                                    </div>

                                    <div x-show="[3, 8].includes(notification?.delivery_option?.id) && notification?.status === 'delivered'" class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 border border-green-200 dark:border-green-800">
                                        <div class="flex items-start gap-2">
                                            <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div class="text-xs text-green-900 dark:text-green-200">
                                                <strong>Success by Absence:</strong> No failure report received from Shoutbomb after the grace period. Notification was successfully delivered.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Log Section -->
                        <div class="mt-6 space-y-4" x-show="notification">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Activity Log</h4>
                                <button
                                    type="button"
                                    x-show="activityEntries && activityEntries.length"
                                    @click="jumpToLatest()"
                                    class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline"
                                >
                                    Jump to latest
                                </button>
                            </div>

                            <!-- Empty state -->
                            <div
                                class="text-xs text-slate-500 dark:text-slate-400"
                                x-show="!activityEntries || !activityEntries.length"
                            >
                                No activity recorded for this notification yet.
                            </div>

                            <!-- Timeline -->
                            <div
                                class="space-y-3"
                                x-show="activityEntries && activityEntries.length"
                            >
                                <template
                                    x-for="(entry, index) in activityEntries || []"
                                    :key="index"
                                >
                                    <div class="flex items-start gap-4">
                                        <div
                                            class="flex-shrink-0 w-40 text-xs text-slate-500 dark:text-slate-400"
                                            x-text="entry.timestamp"
                                        ></div>
                                        <div
                                            class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full"
                                            :class="{
                                                'bg-blue-500': entry.category === 'export',
                                                'bg-emerald-500': entry.category === 'delivery',
                                                'bg-red-500': entry.category === 'failure',
                                                'bg-purple-500': entry.category === 'enhancement',
                                                'bg-slate-400': !entry.category
                                            }"
                                        ></div>
                                        <div class="flex-1">
                                            <div
                                                class="text-sm font-medium text-slate-900 dark:text-white"
                                                x-text="entry.type"
                                            ></div>
                                            <div
                                                class="text-xs text-slate-500 dark:text-slate-400 whitespace-pre-wrap"
                                                x-text="formatActivityDescription(entry)"
                                            ></div>
                                            <div
                                                class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                                                x-show="entry.source || entry.filename || entry.details"
                                            >
                                                <span x-show="entry.source">
                                                    Source:
                                                    <span class="font-mono" x-text="entry.source"></span>
                                                </span>
                                                <span x-show="entry.filename" class="ml-2 flex items-center gap-1">
                                                    <span x-text="formatFileAction(entry)"></span>

                                                    <!-- Summary Report events link to the email detail view -->
                                                    <template x-if="entry.source === 'summary_report' && entry.email_id">
                                                        <a
                                                            class="font-mono text-dcpl-blue dark:text-sky-300 hover:underline"
                                                            :href="'/notices/data/report-emails/' + entry.email_id"
                                                            x-text="entry.filename"
                                                        ></a>
                                                    </template>

                                                    <!-- DB-backed activity rows linked to an ActivityFile -->
                                                    <template x-if="entry.source !== 'summary_report' && entry.activity_file_id">
                                                        <a
                                                            class="font-mono text-dcpl-blue dark:text-sky-300 hover:underline"
                                                            :href="'/notices/data/activity-files/' + entry.activity_file_id"
                                                            x-text="entry.filename"
                                                        ></a>
                                                    </template>

                                                    <!-- Fallback: search export files by filename -->
                                                    <template x-if="entry.source !== 'summary_report' && !entry.activity_file_id">
                                                        <a
                                                            class="font-mono text-dcpl-blue dark:text-sky-300 hover:underline"
                                                            :href="buildFileHref(entry)"
                                                            x-text="entry.filename"
                                                        ></a>
                                                    </template>
                                                </span>
                                                <button
                                                    x-show="entry.details && entry.details.issue_id"
                                                    type="button"
                                                    class="ml-2 text-dcpl-blue dark:text-sky-300 hover:underline"
                                                    @click="$dispatch('issue-detail-open', { id: entry.details.issue_id })"
                                                >
                                                    View Issue #<span x-text="entry.details && entry.details.issue_id ? entry.details.issue_id : ''"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
            </div>
        </div>
    </div>
</div>
