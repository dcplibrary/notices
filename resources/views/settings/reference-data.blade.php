@extends('notices::layouts.app')

@section('title', 'Reference Data Management')

@section('content')
<div class="px-4 sm:px-0" x-data="referenceDataManager()">
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Reference Data Management</h2>
            <p class="mt-1 text-sm text-gray-600">
                Enable/disable notification types, delivery methods, and statuses
            </p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <button @click="activeTab = 'types'"
                    :class="activeTab === 'types' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Notification Types
            </button>
            <button @click="activeTab = 'methods'"
                    :class="activeTab === 'methods' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Delivery Methods
            </button>
            <button @click="activeTab = 'statuses'"
                    :class="activeTab === 'statuses' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Notification Statuses
            </button>
        </nav>
    </div>

    <!-- Notification Types Tab -->
    <div x-show="activeTab === 'types'" class="bg-white shadow rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Notification Types</h3>
            <p class="text-sm text-gray-600 mb-6">
                Control which notification types are active in the system. Disabled types will not appear in filters or reports.
            </p>
            <div class="space-y-3">
                @foreach($notificationTypes as $type)
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500 font-mono">#{{ $type->notification_type_id }}</span>
                        <span class="text-sm font-medium text-gray-900">{{ $type->description }}</span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   :checked="types['{{ $type->notification_type_id }}'].enabled"
                                   @change="toggleType({{ $type->notification_type_id }})"
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-700" x-text="types['{{ $type->notification_type_id }}'].enabled ? 'Enabled' : 'Disabled'"></span>
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Delivery Methods Tab -->
    <div x-show="activeTab === 'methods'" class="bg-white shadow rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Delivery Methods</h3>
            <p class="text-sm text-gray-600 mb-6">
                Control which delivery methods are active. Disabled methods will not appear in filters or reports.
            </p>
            <div class="space-y-3">
                @foreach($deliveryMethods as $method)
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500 font-mono">#{{ $method->delivery_option_id }}</span>
                        <div>
                            <span class="text-sm font-medium text-gray-900">{{ $method->delivery_option }}</span>
                            <p class="text-xs text-gray-500">{{ $method->description }}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   :checked="methods['{{ $method->delivery_option_id }}'].enabled"
                                   @change="toggleMethod({{ $method->delivery_option_id }})"
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-700" x-text="methods['{{ $method->delivery_option_id }}'].enabled ? 'Enabled' : 'Disabled'"></span>
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Notification Statuses Tab -->
    <div x-show="activeTab === 'statuses'" class="bg-white shadow rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Notification Statuses</h3>
            <p class="text-sm text-gray-600 mb-6">
                Control which notification statuses are visible. These are categorized as completed, pending, or failed.
            </p>
            
            @php
                $groupedStatuses = $notificationStatuses->groupBy('category');
            @endphp
            
            @foreach(['completed', 'pending', 'failed'] as $category)
                @if($groupedStatuses->has($category))
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 uppercase">{{ $category }}</h4>
                    <div class="space-y-3">
                        @foreach($groupedStatuses[$category] as $status)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-4">
                                <span class="text-sm text-gray-500 font-mono">#{{ $status->notification_status_id }}</span>
                                <span class="text-sm font-medium text-gray-900">{{ $status->description }}</span>
                            </div>
                            <div class="flex items-center space-x-4">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           :checked="statuses['{{ $status->notification_status_id }}'].enabled"
                                           @change="toggleStatus({{ $status->notification_status_id }})"
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    <span class="ml-3 text-sm font-medium text-gray-700" x-text="statuses['{{ $status->notification_status_id }}'].enabled ? 'Enabled' : 'Disabled'"></span>
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
function referenceDataManager() {
    return {
        activeTab: 'types',
        types: @json($notificationTypes->keyBy('notification_type_id')->map(fn($t) => ['enabled' => $t->enabled, 'display_order' => $t->display_order])),
        methods: @json($deliveryMethods->keyBy('delivery_option_id')->map(fn($m) => ['enabled' => $m->enabled, 'display_order' => $m->display_order])),
        statuses: @json($notificationStatuses->keyBy('notification_status_id')->map(fn($s) => ['enabled' => $s->enabled, 'display_order' => $s->display_order])),
        
        toggleType(id) {
            this.types[id].enabled = !this.types[id].enabled;
            this.updateType(id);
        },
        
        toggleMethod(id) {
            this.methods[id].enabled = !this.methods[id].enabled;
            this.updateMethod(id);
        },
        
        toggleStatus(id) {
            this.statuses[id].enabled = !this.statuses[id].enabled;
            this.updateStatus(id);
        },
        
        async updateType(id) {
            try {
                const response = await fetch(`/notices/settings/notification-type/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        enabled: this.types[id].enabled,
                        display_order: this.types[id].display_order
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to update');
                }
            } catch (error) {
                console.error('Error updating notification type:', error);
                this.types[id].enabled = !this.types[id].enabled; // Revert on error
            }
        },
        
        async updateMethod(id) {
            try {
                const response = await fetch(`/notices/settings/delivery-method/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        enabled: this.methods[id].enabled,
                        display_order: this.methods[id].display_order
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to update');
                }
            } catch (error) {
                console.error('Error updating delivery method:', error);
                this.methods[id].enabled = !this.methods[id].enabled; // Revert on error
            }
        },
        
        async updateStatus(id) {
            try {
                const response = await fetch(`/notices/settings/notification-status/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        enabled: this.statuses[id].enabled,
                        display_order: this.statuses[id].display_order
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to update');
                }
            } catch (error) {
                console.error('Error updating notification status:', error);
                this.statuses[id].enabled = !this.statuses[id].enabled; // Revert on error
            }
        }
    }
}
</script>
@endpush
@endsection
