@props([
    'types' => [],      // Selected notification types: hold, overdue, renew
    'channels' => [],   // Selected channels: email, phone, text
    'statuses' => [],   // Selected statuses: delivered, failed, pending, invalid_contact
])

<div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-4">
    <div class="flex flex-wrap gap-2">
        {{-- Notification Type Pills --}}
        <div class="flex flex-wrap gap-2 pr-4 border-r border-slate-200 dark:border-slate-700">
            <button
                type="button"
                @click="toggleFilter('types', 'hold')"
                :class="filters.types.includes('hold') 
                    ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Hold
            </button>
            <button
                type="button"
                @click="toggleFilter('types', 'overdue')"
                :class="filters.types.includes('overdue') 
                    ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Overdue
            </button>
            <button
                type="button"
                @click="toggleFilter('types', 'renew')"
                :class="filters.types.includes('renew') 
                    ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Renew
            </button>
        </div>

        {{-- Channel Pills --}}
        <div class="flex flex-wrap gap-2 pr-4 border-r border-slate-200 dark:border-slate-700">
            <button
                type="button"
                @click="toggleFilter('channels', 'email')"
                :class="filters.channels.includes('email') 
                    ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Email
            </button>
            <button
                type="button"
                @click="toggleFilter('channels', 'phone')"
                :class="filters.channels.includes('phone') 
                    ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Phone
            </button>
            <button
                type="button"
                @click="toggleFilter('channels', 'text')"
                :class="filters.channels.includes('text') 
                    ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Text Messaging
            </button>
        </div>

        {{-- Status Pills --}}
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                @click="toggleFilter('statuses', 'invalid_contact')"
                :class="filters.statuses.includes('invalid_contact') 
                    ? 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 border-orange-300 dark:border-orange-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Invalid Contact
            </button>
            <button
                type="button"
                @click="toggleFilter('statuses', 'failed')"
                :class="filters.statuses.includes('failed') 
                    ? 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 border-red-300 dark:border-red-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Failed
            </button>
            <button
                type="button"
                @click="toggleFilter('statuses', 'pending')"
                :class="filters.statuses.includes('pending') 
                    ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Pending
            </button>
            <button
                type="button"
                @click="toggleFilter('statuses', 'delivered')"
                :class="filters.statuses.includes('delivered') 
                    ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 border-green-300 dark:border-green-700' 
                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors"
            >
                Delivered
            </button>
        </div>
    </div>
</div>
