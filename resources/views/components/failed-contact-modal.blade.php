<div
    x-data="{
        open: false,
        failedContactId: null,
        failedContact: null,
        loading: false,
        patron_barcode: '',
        patron_first_name: '',
        patron_last_name: '',
        barcodeLocked: false,
        nameLocked: false,
        resolution_notes: '',
        resolution_type: 'updated_phone',

        async openModal(id) {
            this.failedContactId = id;
            this.open = true;
            this.loading = true;
            this.failedContact = null;

            try {
                // Handle both numeric IDs and string IDs
                const idParam = encodeURIComponent(id);
                const response = await fetch(`/notices/api/failed-contacts/${idParam}`);
                const data = await response.json();
                this.failedContact = data;

                // Pre-fill patron fields from representative patron when available
                if (data.patron) {
                    this.patron_barcode = data.patron.patron_barcode || '';
                    this.patron_first_name = data.patron.patron_first_name || '';
                    this.patron_last_name = data.patron.patron_last_name || '';

                    // Lock fields that arrived pre-populated so they are read-only
                    this.barcodeLocked = !!this.patron_barcode;
                    this.nameLocked = !!(this.patron_first_name || this.patron_last_name);
                } else {
                    this.patron_barcode = '';
                    this.patron_first_name = '';
                    this.patron_last_name = '';
                    this.barcodeLocked = false;
                    this.nameLocked = false;
                }

                this.resolution_notes = '';
                this.resolution_type = 'updated_phone';
            } catch (error) {
                console.error('Failed to load failed contact:', error);
                alert('Failed to load failed contact details. Check console for errors.');
                this.open = false;
            } finally {
                this.loading = false;
            }
        },

        closeModal() {
            this.open = false;
            this.failedContactId = null;
            this.failedContact = null;
            this.patron_barcode = '';
            this.patron_first_name = '';
            this.patron_last_name = '';
            this.barcodeLocked = false;
            this.nameLocked = false;
            this.resolution_notes = '';
        },

        resolveActionUrl() {
            if (!this.failedContactId) return '#';
            // Handle both numeric IDs and string IDs
            const id = encodeURIComponent(this.failedContactId);
            return `/notices/failed-contacts/${id}/resolve`;
        },
    }"
    @keydown.escape.window="if (open) closeModal()"
    @failed-contact-open.window="openModal($event.detail.id)"
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
                class="relative bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-3xl w-full mt-8 mb-8"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        Failed Contact Details
                        <span x-show="failedContact" class="text-slate-500 dark:text-slate-400 font-mono text-sm ml-2">#<span x-text="failedContact?.id"></span></span>
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
                    <p class="mt-4 text-slate-600 dark:text-slate-400">Loading failed contact details...</p>
                </div>

                <!-- Content -->
                <div x-show="!loading && failedContact" class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                    <form
                        method="POST"
                        :action="resolveActionUrl()"
                    >
                        @csrf

                        <!-- Contact & Patron Block -->
                        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-6 border border-slate-200 dark:border-slate-700 min-h-[8rem]">
                            <div class="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400">
                                <div class="flex flex-wrap gap-6">
                                    <div class="w-40">{{-- Phone/Email label --}}
                                        <div x-text="failedContact?.string_type === 'phone' ? 'Phone' : 'Email'"></div>
                                    </div>
                                    <div class="w-40">Barcode</div>
                                    <div class="flex-1 min-w-[8rem]">Name</div>
                                </div>
                                <div class="flex flex-wrap gap-6 text-slate-900 dark:text-white mt-0.5">
                                    <!-- Contact value (always read-only) -->
                                    <div class="w-40 font-mono text-sm break-all" x-text="failedContact?.delivery_string"></div>

                                    <!-- Barcode: editable only if not prefilled -->
                                    <div class="w-40">
                                        <template x-if="barcodeLocked">
                                            <div>
                                                <div class="font-mono text-sm" x-text="patron_barcode"></div>
                                                <input type="hidden" name="patron_barcode" :value="patron_barcode">
                                            </div>
                                        </template>
                                        <template x-if="!barcodeLocked">
                                            <input
                                                type="text"
                                                name="patron_barcode"
                                                x-model="patron_barcode"
                                                class="w-full px-2 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                                                placeholder="Add barcode (optional)"
                                            >
                                        </template>
                                    </div>

                                    <!-- Name: editable only if not prefilled -->
                                    <div class="flex-1 min-w-[8rem] text-sm">
                                        <template x-if="nameLocked">
                                            <div>
                                                <span x-text="patron_last_name"></span>
                                                <template x-if="patron_last_name && patron_first_name">
                                                    <span>, </span>
                                                </template>
                                                <span x-text="patron_first_name"></span>
                                                <input type="hidden" name="patron_last_name" :value="patron_last_name">
                                                <input type="hidden" name="patron_first_name" :value="patron_first_name">
                                            </div>
                                        </template>
                                        <template x-if="!nameLocked">
                                            <div class="flex flex-col gap-1">
                                                <div class="flex gap-2">
                                                    <input
                                                        type="text"
                                                        name="patron_last_name"
                                                        x-model="patron_last_name"
                                                        class="w-1/2 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                                                        placeholder="Last name (optional)"
                                                    >
                                                    <input
                                                        type="text"
                                                        name="patron_first_name"
                                                        x-model="patron_first_name"
                                                        class="w-1/2 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                                                        placeholder="First name (optional)"
                                                    >
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <div class="text-slate-600 dark:text-slate-400">
                                            <span x-text="(failedContact?.failure_count || 0) + ' failure' + ((failedContact?.failure_count || 0) === 1 ? '' : 's')"></span>
                                            <template x-if="failedContact?.last_failed_at">
                                                <span>
                                                    • Last: <span x-text="failedContact.last_failed_at"></span>
                                                </span>
                                            </template>
                                        </div>
                                        <div>
                                            <template x-if="failedContact?.patron && failedContact.patron.patron_id">
                                                <a
                                                    :href="`https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/${failedContact.patron.patron_id}/record`"
                                                    target="_blank"
                                                    class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                                >
                                                    View Patron in Polaris
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                    </svg>
                                                </a>
                                            </template>
                                            <template x-if="!failedContact?.patron || !failedContact.patron.patron_id">
                                                <a
                                                    href="https://catalog.dcplibrary.org/leapwebapp"
                                                    target="_blank"
                                                    class="text-xs text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-2"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12l9-9"></path>
                                                    </svg>
                                                    <span x-text="failedContact?.string_type === 'phone' ? 'Search for Phone Number in Polaris' : 'Search in Polaris'"></span>
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Failure Details -->
                        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-6 border border-slate-200 dark:border-slate-700 text-xs min-h-[8rem] mx-6 mb-4">
                            <div class="flex flex-wrap gap-6 mb-2">
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400 mb-0.5">Failure Source</div>
                                    <div class="text-slate-900 dark:text-white" x-text="failedContact?.failure_source || '—'"></div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400 mb-0.5">Affected Patrons</div>
                                    <div class="text-slate-900 dark:text-white" x-text="failedContact?.affected_patron_count ?? 0"></div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400 mb-0.5">Delivery Method</div>
                                    <div class="text-slate-900 dark:text-white" x-text="failedContact?.delivery_option_label || '—'"></div>
                                </div>
                            </div>
                            <template x-if="failedContact?.failure_details">
                                <div class="mt-1">
                                    <div class="text-slate-500 dark:text-slate-400 mb-0.5">Failure Details</div>
                                    <div class="text-slate-900 dark:text-white text-xs whitespace-pre-line" x-text="failedContact.failure_details"></div>
                                </div>
                            </template>
                        </div>

                        <!-- Resolution -->
                        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-6 border border-slate-200 dark:border-slate-700 text-sm min-h-[8rem] mx-6 mb-4">
                            <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Resolution</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-x-6">
                                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                    <input
                                        type="radio"
                                        name="resolution_type"
                                        value="updated_phone"
                                        x-model="resolution_type"
                                        class="text-dcpl-blue border-slate-300 dark:border-slate-600 focus:ring-dcpl-blue"
                                    >
                                    <span>Updated Phone Number</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                    <input
                                        type="radio"
                                        name="resolution_type"
                                        value="changed_to_email"
                                        x-model="resolution_type"
                                        class="text-dcpl-blue border-slate-300 dark:border-slate-600 focus:ring-dcpl-blue"
                                    >
                                    <span>
                                        Changed Delivery Option to
                                        <span class="font-semibold" x-text="failedContact?.delivery_labels?.email || 'Email'"></span>
                                    </span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                    <input
                                        type="radio"
                                        name="resolution_type"
                                        value="changed_to_mail"
                                        x-model="resolution_type"
                                        class="text-dcpl-blue border-slate-300 dark:border-slate-600 focus:ring-dcpl-blue"
                                    >
                                    <span>
                                        Changed Delivery Option to
                                        <span class="font-semibold" x-text="failedContact?.delivery_labels?.mail || 'Mail'"></span>
                                    </span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                    <input
                                        type="radio"
                                        name="resolution_type"
                                        value="other"
                                        x-model="resolution_type"
                                        class="text-dcpl-blue border-slate-300 dark:border-slate-600 focus:ring-dcpl-blue"
                                    >
                                    <span>Other</span>
                                </label>
                            </div>
                        </div>

                        <!-- Notes + Actions -->
                        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-6 border border-slate-200 dark:border-slate-700 text-sm min-h-[8rem] mx-6 mb-6">
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Notes
                            </label>
                            <textarea
                                name="resolution_notes"
                                x-model="resolution_notes"
                                rows="3"
                                class="w-full px-2 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                            ></textarea>

                            <div class="mt-4 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    @click="closeModal()"
                                    class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="px-4 py-2 bg-green-600 dark:bg-green-500 text-white text-xs font-medium rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors"
                                >
                                    Resolve
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
