@extends(notices::layouts.app')

@section('title', 'Notification Settings')

@section('content')
<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-3xl font-bold text-gray-900">Notification Settings</h1>
            <p class="mt-2 text-sm text-gray-700">
                Manage global notification settings. Changes take effect immediately.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="mt-4 rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="mt-4 rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($settings->isEmpty())
        <div class="mt-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No settings configured</h3>
            <p class="mt-1 text-sm text-gray-500">Settings will appear here once they are created in the database.</p>
        </div>
    @else
        @foreach($settings as $group => $groupSettings)
            <div class="mt-8">
                <div class="mb-4">
                    <h2 class="text-xl font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $group) }}</h2>
                    <div class="mt-2 border-t border-gray-200"></div>
                </div>

                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <ul role="list" class="divide-y divide-gray-200">
                        @foreach($groupSettings as $setting)
                            <li>
                                <div class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center">
                                                <p class="text-sm font-medium text-indigo-600 truncate">
                                                    {{ $setting->full_key }}
                                                </p>
                                                @if($setting->is_sensitive)
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Sensitive
                                                    </span>
                                                @endif
                                                @if(!$setting->is_editable)
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        Read-only
                                                    </span>
                                                @endif
                                            </div>
                                            @if($setting->description)
                                                <p class="mt-1 text-sm text-gray-500">{{ $setting->description }}</p>
                                            @endif
                                            <div class="mt-2 flex items-center text-sm text-gray-500">
                                                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                                    @if($setting->shouldHide())
                                                        {{ $setting->getMaskedValue() }}
                                                    @else
                                                        {{ is_array($setting->getTypedValue()) ? json_encode($setting->getTypedValue()) : $setting->getTypedValue() }}
                                                    @endif
                                                </span>
                                                <span class="ml-2 text-gray-400">|</span>
                                                <span class="ml-2">Type: <span class="font-medium">{{ $setting->type }}</span></span>
                                            </div>
                                            @if($setting->updated_by)
                                                <p class="mt-1 text-xs text-gray-400">
                                                    Last updated by {{ $setting->updated_by }} on {{ $setting->updated_at->format('M d, Y H:i:s') }}
                                                </p>
                                            @endif
                                        </div>
                                        <div class="ml-5 flex-shrink-0 flex space-x-2">
                                            @if($setting->is_editable)
                                                <a href="{{ route('notices.settings.edit', $setting->id) }}"
                                                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                    Edit
                                                </a>
                                                <form action="{{ route('notices.settings.destroy', $setting->id) }}"
                                                      method="POST"
                                                      class="inline"
                                                      onsubmit="return confirm('Delete this setting? It will revert to config default.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-1.5 border border-red-300 shadow-sm text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
