@extends('notices::layouts.app')

@section('title', 'Edit Setting - ' . $setting->full_key)

@section('content')
<div class="px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <a href="{{ route('notices.settings.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900 flex items-center">
                <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Settings
            </a>
            <h1 class="mt-4 text-3xl font-bold text-gray-900">Edit Setting</h1>
            <p class="mt-2 text-sm text-gray-600">{{ $setting->full_key }}</p>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-md bg-red-50 p-4">
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

        <!-- Form -->
        <div class="bg-white shadow sm:rounded-lg">
            <form action="{{ route('notices.settings.update', $setting->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="px-4 py-5 sm:p-6">
                    <!-- Setting Info -->
                    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Group</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $setting->group }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Key</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $setting->key }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Type</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $setting->type }}</dd>
                        </div>
                        @if($setting->scope)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Scope</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $setting->scope }} ({{ $setting->scope_id }})</dd>
                            </div>
                        @endif
                    </div>

                    @if($setting->description)
                        <div class="mb-6">
                            <p class="text-sm text-gray-600">{{ $setting->description }}</p>
                        </div>
                    @endif

                    <!-- Value Input -->
                    <div>
                        <label for="value" class="block text-sm font-medium text-gray-700 mb-2">
                            Value
                            @if($setting->validation_rules)
                                <span class="text-xs text-gray-500">({{ implode(', ', (array)$setting->validation_rules) }})</span>
                            @endif
                        </label>

                        @if($setting->type === 'bool' || $setting->type === 'boolean')
                            <select id="value" name="value" required
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="true" {{ $setting->getTypedValue() ? 'selected' : '' }}>True</option>
                                <option value="false" {{ !$setting->getTypedValue() ? 'selected' : '' }}>False</option>
                            </select>
                        @elseif($setting->type === 'json' || $setting->type === 'array')
                            <textarea id="value" name="value" rows="8" required
                                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md font-mono"
                                      placeholder='{"key": "value"}'>{{ old('value', json_encode($setting->getTypedValue(), JSON_PRETTY_PRINT)) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Enter valid JSON</p>
                        @elseif($setting->is_sensitive)
                            <input type="password" id="value" name="value" required
                                   value="{{ old('value') }}"
                                   placeholder="Enter new value"
                                   class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <p class="mt-1 text-xs text-gray-500">Enter a new value to update. Leave blank if you don't want to change it.</p>
                        @elseif(in_array($setting->type, ['int', 'integer', 'float', 'decimal']))
                            <input type="number"
                                   id="value"
                                   name="value"
                                   required
                                   step="{{ in_array($setting->type, ['float', 'decimal']) ? '0.01' : '1' }}"
                                   value="{{ old('value', $setting->getTypedValue()) }}"
                                   class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        @else
                            <input type="text" id="value" name="value" required
                                   value="{{ old('value', $setting->getTypedValue()) }}"
                                   class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        @endif
                    </div>

                    <!-- Current Value Display -->
                    @if(!$setting->shouldHide())
                        <div class="mt-4 p-3 bg-gray-50 rounded-md">
                            <p class="text-xs font-medium text-gray-700 mb-1">Current Value:</p>
                            <pre class="text-xs text-gray-600 whitespace-pre-wrap break-all">{{ is_array($setting->getTypedValue()) ? json_encode($setting->getTypedValue(), JSON_PRETTY_PRINT) : $setting->getTypedValue() }}</pre>
                        </div>
                    @endif

                    <!-- Audit Info -->
                    @if($setting->updated_by)
                        <div class="mt-4 text-xs text-gray-500">
                            Last updated by <span class="font-medium">{{ $setting->updated_by }}</span> on {{ $setting->updated_at->format('M d, Y H:i:s') }}
                        </div>
                    @endif
                </div>

                <!-- Actions -->
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Save Changes
                    </button>
                    <a href="{{ route('notices.settings.index') }}"
                       class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
