@extends('notices::layouts.user-portal')

@section('title', 'Notification Status')

@section('body')
<div class="notices-staff-page pb-10">
    <div class="max-w-7xl mx-auto w-full" x-data="notificationPortal()">
        
        {{-- Filter Panel (matching admin structure) --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-6 mb-6 shadow-sm">
            {{-- Search row --}}
            <div class="flex flex-col md:flex-row md:items-end gap-2 max-w-2xl">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Search</label>
                    <input
                        type="text"
                        x-model="filters.search"
                        @keydown.enter.prevent="search()"
                        placeholder="Patron, barcode, title, or item..."
                        class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue"
                    />
                </div>
                <div class="flex flex-row gap-2 md:ml-2">
                    <button
                        @click="search()"
                        :disabled="loading"
                        class="h-10 px-4 bg-dcpl-blue hover:brightness-95 disabled:opacity-55 text-white rounded-lg font-medium transition-colors shadow-sm flex items-center justify-center text-sm gap-2"
                    >
                        <svg x-show="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="loading ? 'Searching...' : 'Search'"></span>
                    </button>
                    <button
                        @click="clearFilters()"
                        class="h-10 px-4 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg font-medium transition-colors flex items-center justify-center text-sm"
                    >
                        Clear
                    </button>
                </div>
            </div>

            {{-- Date Range and Status Pills row --}}
            <div class="mt-4 flex flex-col sm:flex-row sm:items-end gap-4">
                {{-- Date Range --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Date Range</label>
                    <div class="flex flex-wrap items-center gap-2">
                    <select
                        x-model="quickRange"
                        @change="applyQuickRange(); search()"
                        class="h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                    >
                        <option value="yesterday">Yesterday</option>
                        <option value="today">Today</option>
                        <option value="7days">Last 7 Days</option>
                        <option value="thismonth">This Month</option>
                        <option value="lastmonth">Last Month</option>
                        <option value="all">All Time</option>
                        <option value="custom" x-show="quickRange === 'custom'">Custom</option>
                    </select>
                    <input
                        type="date"
                        x-model="filters.date_from"
                        @change="quickRange = 'custom'"
                        class="h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                    />
                    <span class="text-slate-500 dark:text-slate-400">to</span>
                    <input
                        type="date"
                        x-model="filters.date_to"
                        @change="quickRange = 'custom'"
                        class="h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                    />
                    <button
                        x-show="quickRange === 'custom'"
                        @click="search()"
                        :disabled="loading"
                        class="h-10 px-4 bg-dcpl-blue hover:brightness-95 disabled:opacity-55 text-white rounded-lg font-medium transition-colors shadow-sm text-sm"
                    >
                        Apply
                    </button>
                    </div>
                </div>

                {{-- Status Pills --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Status</label>
                    <div class="flex items-center gap-1">
                        <button
                            @click="filters.status = filters.status === 'delivered' ? '' : 'delivered'; search()"
                            :class="filters.status === 'delivered' 
                                ? 'bg-emerald-600 text-white' 
                                : 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-900/60'"
                            class="h-10 px-3 text-sm font-medium rounded-lg transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Delivered
                        </button>
                        <button
                            @click="filters.status = filters.status === 'pending' ? '' : 'pending'; search()"
                            :class="filters.status === 'pending' 
                                ? 'bg-amber-500 text-white' 
                                : 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-900/60'"
                            class="h-10 px-3 text-sm font-medium rounded-lg transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Pending
                        </button>
                        <button
                            @click="filters.status = filters.status === 'failed' ? '' : 'failed'; search()"
                            :class="filters.status === 'failed' 
                                ? 'bg-red-600 text-white' 
                                : 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/60'"
                            class="h-10 px-3 text-sm font-medium rounded-lg transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Failed
                        </button>
                    </div>
                </div>
            </div>

            {{-- Advanced filters toggle --}}
            <div class="flex justify-end pt-3">
                <button
                    type="button"
                    @click="showAdvanced = !showAdvanced"
                    class="inline-flex items-center gap-1 text-xs font-medium text-dcpl-blue dark:text-sky-300 hover:text-blue-700 dark:hover:text-blue-300"
                >
                    <span x-show="!showAdvanced">Show Advanced Filters</span>
                    <span x-show="showAdvanced" x-cloak>Hide Advanced Filters</span>
                    <svg x-show="!showAdvanced" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    <svg x-show="showAdvanced" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>

            {{-- Advanced filters --}}
            <div x-show="showAdvanced" x-cloak class="border-t border-slate-200 dark:border-slate-700 pt-4 mt-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Notification Type --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Notification Type</label>
                        <select
                            x-model="filters.notification_type"
                            @change="search()"
                            class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                            <option value="">All Types</option>
                            @foreach($notification_types as $type)
                            <option value="{{ $type->id }}">{{ $type->display_label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Delivery Method --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Delivery Method</label>
                        <select
                            x-model="filters.delivery_option_id"
                            @change="search()"
                            class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-dcpl-blue text-sm"
                        >
                            <option value="">All Methods</option>
                            @foreach($delivery_options as $option)
                            <option value="{{ $option->id }}">{{ $option->display_label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        @click="search()"
                        :disabled="loading"
                        class="px-4 py-2 bg-dcpl-blue hover:brightness-95 disabled:opacity-55 text-white rounded-lg font-medium transition-colors shadow-sm text-sm"
                    >
                        Apply Filters
                    </button>
                    <button
                        @click="clearFilters()"
                        class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg font-medium transition-colors text-sm"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        {{-- Results Summary --}}
        <div class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            <span x-show="meta.total !== null">
                Showing <span x-text="meta.from || 0"></span> - <span x-text="meta.to || 0"></span> of <span x-text="meta.total"></span> notifications
            </span>
            <span x-show="loading" class="ml-2 inline-flex items-center gap-1.5 text-dcpl-blue dark:text-sky-300">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Loading...</span>
            </span>
        </div>

        {{-- Notifications Table --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
            {{-- Loading Overlay --}}
            <div x-show="loading" class="absolute inset-0 bg-white/60 dark:bg-slate-900/60 z-10 flex items-center justify-center" style="display: none;">
                <div class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700">
                    <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Searching notifications...</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                <button @click="sortBy('patron_barcode')" class="inline-flex items-center gap-1 hover:text-dcpl-blue dark:hover:text-sky-300 transition-colors" :class="sortColumn === 'patron_barcode' ? 'text-dcpl-blue dark:text-sky-300' : 'text-slate-500 dark:text-slate-400'">
                                    <span>Patron</span>
                                    <span x-show="sortColumn === 'patron_barcode'" class="text-xs" x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                <button @click="sortBy('item_title')" class="inline-flex items-center gap-1 hover:text-dcpl-blue dark:hover:text-sky-300 transition-colors" :class="sortColumn === 'item_title' ? 'text-dcpl-blue dark:text-sky-300' : 'text-slate-500 dark:text-slate-400'">
                                    <span>Item</span>
                                    <span x-show="sortColumn === 'item_title'" class="text-xs" x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                <button @click="sortBy('notification_type')" class="inline-flex items-center gap-1 hover:text-dcpl-blue dark:hover:text-sky-300 transition-colors" :class="sortColumn === 'notification_type' ? 'text-dcpl-blue dark:text-sky-300' : 'text-slate-500 dark:text-slate-400'">
                                    <span>Type</span>
                                    <span x-show="sortColumn === 'notification_type'" class="text-xs" x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                <button @click="sortBy('delivery_option_id')" class="inline-flex items-center gap-1 hover:text-dcpl-blue dark:hover:text-sky-300 transition-colors" :class="sortColumn === 'delivery_option_id' ? 'text-dcpl-blue dark:text-sky-300' : 'text-slate-500 dark:text-slate-400'">
                                    <span>Method</span>
                                    <span x-show="sortColumn === 'delivery_option_id'" class="text-xs" x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                <button @click="sortBy('status')" class="inline-flex items-center gap-1 hover:text-dcpl-blue dark:hover:text-sky-300 transition-colors" :class="sortColumn === 'status' ? 'text-dcpl-blue dark:text-sky-300' : 'text-slate-500 dark:text-slate-400'">
                                    <span>Status</span>
                                    <span x-show="sortColumn === 'status'" class="text-xs" x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                <button @click="sortBy('sent_timestamp')" class="inline-flex items-center gap-1 hover:text-dcpl-blue dark:hover:text-sky-300 transition-colors" :class="sortColumn === 'sent_timestamp' ? 'text-dcpl-blue dark:text-sky-300' : 'text-slate-500 dark:text-slate-400'">
                                    <span>Date Sent</span>
                                    <span x-show="sortColumn === 'sent_timestamp'" class="text-xs" x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <template x-if="notifications.length === 0 && !loading">
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                    </svg>
                                    <p class="text-slate-500 dark:text-slate-400 font-medium">No notifications found</p>
                                    <p class="text-slate-400 dark:text-slate-500 text-sm mt-1">Try adjusting your search or filters</p>
                                </td>
                            </tr>
                        </template>
                        <template x-for="notification in notifications" :key="notification.id">
                            <tr 
                                @click="openDetail(notification.id)"
                                class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors cursor-pointer"
                            >
                                {{-- Patron --}}
                                <td class="px-6 py-4 text-sm">
                                    <template x-if="notification.patron_name">
                                        <div class="text-slate-700 dark:text-slate-300 mb-1" x-text="notification.patron_name"></div>
                                    </template>
                                    <span class="text-slate-600 dark:text-slate-300 font-mono text-xs" x-text="notification.patron_barcode"></span>
                                </td>

                                {{-- Item --}}
                                <td class="px-6 py-4 text-sm">
                                    <template x-if="notification.item_title">
                                        <div class="text-slate-700 dark:text-slate-300 mb-1 max-w-xs truncate" x-text="notification.item_title"></div>
                                    </template>
                                    <template x-if="notification.item_barcode">
                                        <span class="text-slate-500 dark:text-slate-500 font-mono text-xs" x-text="notification.item_barcode"></span>
                                    </template>
                                </td>

                                {{-- Type --}}
                                <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300" x-text="notification.notification_type"></td>

                                {{-- Method (icon based on delivery_option_icon from DeliveryOption model) --}}
                                <td class="px-6 py-4 text-sm">
                                    <template x-if="notification.delivery_option_icon === 'mail'">
                                        <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" :title="notification.delivery_option_label">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                    </template>
                                    <template x-if="notification.delivery_option_icon === 'email'">
                                        <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" :title="notification.delivery_option_label">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                        </svg>
                                    </template>
                                    <template x-if="notification.delivery_option_icon === 'phone'">
                                        <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" :title="notification.delivery_option_label">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                    </template>
                                    <template x-if="notification.delivery_option_icon === 'sms'">
                                        <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" :title="notification.delivery_option_label">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                        </svg>
                                    </template>
                                    <template x-if="notification.delivery_option_icon === 'unknown' || !notification.delivery_option_icon">
                                        <span class="text-slate-500 dark:text-slate-500 text-xs" x-text="notification.delivery_option_label || '?'"></span>
                                    </template>
                                </td>

                                {{-- Status (with icons) --}}
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        <template x-if="notification.status === 'delivered'">
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </template>
                                        <template x-if="notification.status === 'failed'">
                                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </template>
                                        <template x-if="notification.status === 'pending'">
                                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </template>
                                        <span class="capitalize text-slate-800 dark:text-slate-200" x-text="notification.status"></span>
                                    </div>
                                </td>

                                {{-- Date Sent --}}
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <template x-if="notification.sent_date">
                                        <div>
                                            <div x-text="notification.sent_date"></div>
                                            <div class="text-xs text-slate-500 dark:text-slate-500" x-text="notification.sent_time"></div>
                                        </div>
                                    </template>
                                    <template x-if="!notification.sent_date">
                                        <span class="text-slate-500 dark:text-slate-500">—</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div x-show="meta.last_page > 1" class="px-4 py-3 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div class="text-sm text-slate-600 dark:text-slate-400">
                    Page <span x-text="meta.current_page"></span> of <span x-text="meta.last_page"></span>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="goToPage(meta.current_page - 1)"
                        :disabled="meta.current_page <= 1"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >Previous</button>
                    <button
                        @click="goToPage(meta.current_page + 1)"
                        :disabled="!meta.has_more_pages"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >Next</button>
                </div>
            </div>
        </div>

        {{-- Detail Modal --}}
        <x-notices::unified-notification-modal :admin="false" />
    </div>
</div>

@push('scripts')
<script>
function notificationPortal() {
    return {
        loading: false,
        showAdvanced: false,
        quickRange: '{{ $initial_quick_range ?? 'yesterday' }}',
        hasSearched: false, // Track if user has performed a text search
        sortColumn: 'sent_timestamp',
        sortDir: 'desc',
        filters: {
            search: '{{ $filters['search'] ?? '' }}',
            date_from: '{{ $filters['date_from'] ?? '' }}',
            date_to: '{{ $filters['date_to'] ?? '' }}',
            status: '{{ $filters['status'] ?? '' }}',
            notification_type: '{{ $filters['notification_type'] ?? '' }}',
            delivery_option_id: '{{ $filters['delivery_option_id'] ?? '' }}',
        },
        notifications: @json($formatted_notifications),
        meta: {
            current_page: {{ $notifications->currentPage() }},
            last_page: {{ $notifications instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $notifications->lastPage() : 'null' }},
            per_page: {{ $notifications->perPage() }},
            total: {{ $notifications instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $notifications->total() : 'null' }},
            from: {{ $notifications->firstItem() ?? 'null' }},
            to: {{ $notifications->lastItem() ?? 'null' }},
            has_more_pages: {{ $notifications->hasMorePages() ? 'true' : 'false' }},
        },
        page: 1,

        init() {
            // Set initial date range based on quick range
            if (!this.filters.date_from && !this.filters.date_to) {
                this.applyQuickRange();
            }
        },

        // Clear all filters and reset to defaults
        clearFilters() {
            this.filters.search = '';
            this.filters.status = '';
            this.filters.notification_type = '';
            this.filters.delivery_option_id = '';
            this.hasSearched = false;
            this.quickRange = 'yesterday';
            this.applyQuickRange();
            this.search();
        },

        // Sort notifications by column (client-side)
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDir = 'asc';
            }

            this.notifications = [...this.notifications].sort((a, b) => {
                let aVal = a[column];
                let bVal = b[column];

                // Handle null/undefined values
                if (aVal == null) aVal = '';
                if (bVal == null) bVal = '';

                // Numeric comparison for timestamps and IDs
                if (column === 'sent_timestamp' || column === 'delivery_option_id') {
                    aVal = Number(aVal) || 0;
                    bVal = Number(bVal) || 0;
                } else {
                    // String comparison (case-insensitive)
                    aVal = String(aVal).toLowerCase();
                    bVal = String(bVal).toLowerCase();
                }

                if (aVal < bVal) return this.sortDir === 'asc' ? -1 : 1;
                if (aVal > bVal) return this.sortDir === 'asc' ? 1 : -1;
                return 0;
            });
        },

        applyQuickRange() {
            const today = new Date();
            const formatDate = (d) => d.toISOString().split('T')[0];

            switch (this.quickRange) {
                case 'today':
                    this.filters.date_from = formatDate(today);
                    this.filters.date_to = formatDate(today);
                    break;
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    this.filters.date_from = formatDate(yesterday);
                    this.filters.date_to = formatDate(yesterday);
                    break;
                case '7days':
                    const sevenDaysAgo = new Date(today);
                    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
                    this.filters.date_from = formatDate(sevenDaysAgo);
                    this.filters.date_to = formatDate(today);
                    break;
                case 'thismonth':
                    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                    this.filters.date_from = formatDate(firstDay);
                    this.filters.date_to = formatDate(today);
                    break;
                case 'lastmonth':
                    const lastMonthFirst = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const lastMonthLast = new Date(today.getFullYear(), today.getMonth(), 0);
                    this.filters.date_from = formatDate(lastMonthFirst);
                    this.filters.date_to = formatDate(lastMonthLast);
                    break;
                case 'all':
                    this.filters.date_from = '';
                    this.filters.date_to = '';
                    break;
            }
        },

        // Build common filter params for API calls
        buildFilterParams() {
            const params = new URLSearchParams();
            if (this.filters.search) params.append('search', this.filters.search);
            if (this.filters.date_from) params.append('date_from', this.filters.date_from);
            if (this.filters.date_to) params.append('date_to', this.filters.date_to);
            if (this.filters.status) params.append('status', this.filters.status);
            if (this.filters.notification_type) params.append('notification_type', this.filters.notification_type);
            if (this.filters.delivery_option_id) params.append('delivery_option_id', this.filters.delivery_option_id);
            return params;
        },

        async search() {
            this.loading = true;
            this.page = 1;

            try {
                // Search takes precedence: if search has a value and this is a new search,
                // clear date range to search all dates
                if (this.filters.search && !this.hasSearched) {
                    this.filters.date_from = '';
                    this.filters.date_to = '';
                    this.quickRange = 'all';
                    this.hasSearched = true;
                }
                
                // If search is cleared, reset to date-based filtering
                if (!this.filters.search && this.hasSearched) {
                    this.hasSearched = false;
                    this.quickRange = 'yesterday';
                    this.applyQuickRange();
                }
                
                const params = this.buildFilterParams();
                params.append('page', this.page);

                const response = await fetch(`{{ route('notices.user-portal.search') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();
                this.notifications = data.data;
                this.meta = data.meta;
            } catch (error) {
                console.error('Search failed:', error);
            } finally {
                this.loading = false;
            }
        },

        async goToPage(page) {
            if (page < 1 || (this.meta.last_page && page > this.meta.last_page)) return;
            
            this.loading = true;
            this.page = page;

            try {
                const params = this.buildFilterParams();
                params.append('page', page);

                const response = await fetch(`{{ route('notices.user-portal.search') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();
                this.notifications = data.data;
                this.meta = data.meta;
            } catch (error) {
                console.error('Pagination failed:', error);
            } finally {
                this.loading = false;
            }
        },

        openDetail(id) {
            this.$dispatch('user-portal-detail-open', { id });
        }
    };
}
</script>
@endpush
@endsection
