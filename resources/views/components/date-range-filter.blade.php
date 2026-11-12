@props([
    'dateFrom' => null,
    'dateTo' => null,
    'quickRange' => 'all',
    'showQuickSelect' => true,
    'autoSubmit' => true,
    // variant "inline" renders full desktop controls; "button" shows only the funnel button
    'variant' => 'inline',
])

<div x-data="{
    dateFrom: '{{ $dateFrom ?: '' }}',
    dateTo: '{{ $dateTo ?: '' }}',
    quickRange: '{{ $quickRange ?: 'all' }}',
    showCustom: false,
    initialized: false,

    applyQuickRange() {
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        switch(this.quickRange) {
            case 'all':
                // All Time: clear explicit date filters so the backend does not
                // constrain by date unless the user chooses a specific range.
                this.dateFrom = '';
                this.dateTo = '';
                break;
            case 'today':
                this.dateFrom = this.formatDate(today);
                this.dateTo = this.formatDate(today);
                break;
            case 'yesterday':
                this.dateFrom = this.formatDate(yesterday);
                this.dateTo = this.formatDate(yesterday);
                break;
            case '7days':
                const sevenDaysAgo = new Date(today);
                sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
                this.dateFrom = this.formatDate(sevenDaysAgo);
                this.dateTo = this.formatDate(today);
                break;
            case 'thismonth':
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                this.dateFrom = this.formatDate(firstDay);
                this.dateTo = this.formatDate(today);
                break;
            case 'lastmonth':
                const lastMonthFirst = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const lastMonthLast = new Date(today.getFullYear(), today.getMonth(), 0);
                this.dateFrom = this.formatDate(lastMonthFirst);
                this.dateTo = this.formatDate(lastMonthLast);
                break;
            case 'thisyear':
                const yearFirst = new Date(today.getFullYear(), 0, 1);
                this.dateFrom = this.formatDate(yearFirst);
                this.dateTo = this.formatDate(today);
                break;
        }

        this.showCustom = false;

        @if($autoSubmit)
        // Only auto-submit if this is a user action (not initial load)
        if (this.initialized) {
            this.$nextTick(() => {
                this.$el.closest('form').submit();
            });
        }
        @endif
    },

    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    },

    formatDisplayDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString + 'T00:00:00');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const year = date.getFullYear();
        return `${month}/${day}/${year}`;
    },

    checkIfCustom() {
        // Check if current dates match any quick range
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        const todayStr = this.formatDate(today);
        const yesterdayStr = this.formatDate(yesterday);

        if (this.dateFrom === todayStr && this.dateTo === todayStr) {
            this.quickRange = 'today';
        } else if (this.dateFrom === yesterdayStr && this.dateTo === yesterdayStr) {
            this.quickRange = 'yesterday';
        }
    }
}" x-init="
    if (!dateFrom || !dateTo || dateFrom === '' || dateTo === '') applyQuickRange();
    setTimeout(() => { initialized = true; }, 500);
">

    <input type="hidden" name="date_from" x-model="dateFrom">
    <input type="hidden" name="date_to" x-model="dateTo">
    <input type="hidden" name="quick_range" x-model="quickRange">

    <div class="flex items-center gap-2">
        @if($variant === 'inline' && $showQuickSelect)
        <!-- Quick Range Dropdown -->
        <select
            x-model="quickRange"
            @change="applyQuickRange()"
            class="h-10 px-3 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
        >
            <option value="all">All Time</option>
            <option value="today">Today</option>
            <option value="yesterday">Yesterday</option>
            <option value="7days">Last 7 Days</option>
            <option value="thismonth">This Month</option>
            <option value="lastmonth">Last Month</option>
            <option value="thisyear">This Year</option>
        </select>
        @endif

        @if($variant === 'inline')
        <!-- Custom Date Range (desktop inline) -->
        <div class="hidden md:flex items-center gap-2">
            <!-- Date From -->
            <input
                type="date"
                x-model="dateFrom"
                @change="checkIfCustom()"
                class="h-10 px-3 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                placeholder="mm/dd/yyyy"
            >

            <span class="text-slate-500 dark:text-slate-400">to</span>

            <!-- Date To -->
            <input
                type="date"
                x-model="dateTo"
                @change="checkIfCustom()"
                class="h-10 px-3 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                placeholder="mm/dd/yyyy"
            >

            @if(!$autoSubmit)
            <!-- Apply Button (only when autoSubmit is false) -->
            <button
                type="submit"
                class="px-4 py-2 bg-dcpl-blue text-white rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors text-sm font-medium"
            >
                Apply
            </button>
            @endif
        </div>
        @endif

        <!-- Funnel-style button (always visible in compact mode, mobile-only in inline mode) -->
        <div class="{{ $variant === 'inline' ? 'md:hidden flex' : 'flex' }} items-center gap-2">
            <button
                type="button"
                @click="$refs.mobileFilterDialog.showModal()"
                class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V4m8 3V4M5 9h14M6 4h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2z" />
                </svg>
                <span class="text-sm" x-text="formatDisplayDate(dateFrom) + (dateFrom !== dateTo ? ' - ' + formatDisplayDate(dateTo) : '')"></span>
            </button>
        </div>
    </div>

    <!-- Filter Modal -->
    <dialog x-ref="mobileFilterDialog" class="{{ $variant === 'inline' ? 'md:hidden ' : '' }}backdrop:bg-black/50 bg-white dark:bg-slate-800 rounded-lg shadow-xl p-0 max-w-sm w-full">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Date Range</h3>
                <button @click="$refs.mobileFilterDialog.close()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-2">
                <!-- Quick Range for Mobile -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Quick Select</label>
                    <select
                        x-model="quickRange"
                        @change="applyQuickRange()"
                        class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                    >
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="7days">Last 7 Days</option>
                        <option value="thismonth">This Month</option>
                        <option value="lastmonth">Last Month</option>
                        <option value="thisyear">This Year</option>
                    </select>
                </div>

                <!-- Custom Date Inputs for Mobile -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">From</label>
                        <input
                            type="date"
                            x-model="dateFrom"
                            class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">To</label>
                        <input
                            type="date"
                            x-model="dateTo"
                            class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                    </div>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button
                    type="button"
                    @click="$refs.mobileFilterDialog.close()"
                    class="flex-1 px-4 py-2 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    @click="$refs.mobileFilterDialog.close()"
                    class="flex-1 px-4 py-2 bg-dcpl-blue text-white rounded-lg hover:brightness-95 dark:hover:brightness-95 transition-colors"
                >
                    Apply
                </button>
            </div>
        </div>
    </dialog>
</div>
