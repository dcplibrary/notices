@extends('notices::layouts.app')

@section('title', 'API Tokens')

@section('body')
<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2">API Tokens</h1>
                <p class="text-slate-400">Generate and manage API tokens for accessing the Notices API</p>
            </div>
            <a href="{{ route('notices.api-docs') }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-dcpl-blue hover:brightness-95 text-white rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
                View API Docs
            </a>
        </div>
    </div>

    <!-- Info Box -->
    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-dcpl-blue dark:text-sky-300 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-300">
                <p class="font-semibold mb-1">About API Tokens</p>
                <p>API tokens allow external applications to access the Notices API. Each token is tied to your user account and can be revoked at any time. Keep your tokens secure and never share them publicly.</p>
            </div>
        </div>
    </div>

    <!-- Create New Token Form -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">Create New Token</h2>
        
        <form method="POST" action="{{ route('notices.settings.api-tokens.create') }}" x-data="{ showToken: false, newToken: '' }">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="token_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Token Name
                    </label>
                    <input 
                        type="text" 
                        id="token_name" 
                        name="name" 
                        required
                        placeholder="e.g., Dashboard App, Mobile App, Script"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-dcpl-blue focus:border-dcpl-blue"
                    >
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">A descriptive name to help you identify this token</p>
                </div>
                
                <div>
                    <label for="abilities" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Abilities (Permissions)
                    </label>
                    <select 
                        id="abilities" 
                        name="abilities[]" 
                        multiple
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-dcpl-blue focus:border-dcpl-blue"
                    >
                        <option value="*" selected>All Abilities</option>
                        <option value="notices:read">Read Notifications</option>
                        <option value="notices:write">Write Notifications</option>
                        <option value="issues:read">Read Issues</option>
                        <option value="issues:write">Write Issues</option>
                        <option value="admin:maintenance">Admin: Maintenance (run imports/cleanup)</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Hold Ctrl/Cmd to select multiple</p>
                </div>
            </div>

            <button 
                type="submit"
                class="px-4 py-2 bg-dcpl-blue hover:brightness-95 text-white rounded-lg transition-colors"
            >
                Generate Token
            </button>
        </form>

        <!-- Display new token after creation -->
        @if(isset($newToken) && $newToken)
        <div x-data="{ showToken: true, copied: false }" x-show="showToken" class="mt-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700/50 rounded-lg">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-semibold text-green-900 dark:text-green-200 mb-1">Token Created Successfully!</h3>
                    <p class="text-sm text-green-800 dark:text-green-300">Copy this token now. You won't be able to see it again.</p>
                </div>
                <button @click="showToken = false" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <input 
                    type="text" 
                    readonly 
                    value="{{ $newToken }}"
                    id="new-token-input"
                    class="flex-1 px-3 py-2 bg-white dark:bg-slate-700 border border-green-300 dark:border-green-600 rounded-lg font-mono text-sm"
                >
                <button 
                    @click="navigator.clipboard.writeText('{{ $newToken }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
                >
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
            </div>
        </div>
        @endif
    </div>

    <!-- Existing Tokens -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">Your API Tokens</h2>
        
        @if($tokens && $tokens->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700">
                        <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Name</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Abilities</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Last Used</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Created</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokens as $token)
                    <tr class="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                        <td class="py-3 px-4">
                            <div class="font-medium text-slate-900 dark:text-white">{{ $token->name }}</div>
                            @if($token->token)
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-1">
                                {{ substr($token->token, 0, 20) }}...
                            </div>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            @if(in_array('*', $token->abilities))
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">All</span>
                            @else
                            <div class="flex flex-wrap gap-1">
                                @foreach($token->abilities as $ability)
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">{{ $ability }}</span>
                                @endforeach
                            </div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-sm text-slate-600 dark:text-slate-400">
                            {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never' }}
                        </td>
                        <td class="py-3 px-4 text-sm text-slate-600 dark:text-slate-400">
                            {{ $token->created_at->format('M j, Y') }}
                        </td>
                        <td class="py-3 px-4 text-right">
                            <form method="POST" action="{{ route('notices.settings.api-tokens.revoke', $token->id) }}" onsubmit="return confirm('Are you sure you want to revoke this token?')">
                                @csrf
                                @method('DELETE')
                                <button 
                                    type="submit"
                                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm font-medium"
                                >
                                    Revoke
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto text-slate-400 dark:text-slate-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
            </svg>
            <p class="text-slate-600 dark:text-slate-400 mb-2">No API tokens created yet</p>
            <p class="text-sm text-slate-500 dark:text-slate-500">Create your first token above to start using the API</p>
        </div>
        @endif
    </div>

    <!-- Quick Start Guide -->
    <div class="mt-6 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Quick Start</h3>
        <div class="space-y-4 text-sm text-slate-700 dark:text-slate-300">
            <div>
                <p class="font-medium mb-1">1. Generate a token</p>
                <p class="text-slate-600 dark:text-slate-400">Create a new token with a descriptive name and appropriate permissions.</p>
            </div>
            <div>
                <p class="font-medium mb-1">2. Copy the token</p>
                <p class="text-slate-600 dark:text-slate-400">After creation, copy the token immediately. You won't be able to see it again.</p>
            </div>
            <div>
                <p class="font-medium mb-1">3. Use in API requests</p>
                <p class="text-slate-600 dark:text-slate-400">Include the token in the Authorization header:</p>
                <code class="block mt-2 p-2 bg-slate-800 dark:bg-slate-900 text-green-400 rounded text-xs">Authorization: Bearer YOUR_TOKEN</code>
            </div>
            <div>
                <p class="font-medium mb-1">4. Test in Swagger UI</p>
                <p class="text-slate-600 dark:text-slate-400">Use the <a href="{{ route('notices.api-docs') }}" class="text-dcpl-blue dark:text-sky-300 hover:underline">API Documentation</a> page to test endpoints interactively.</p>
            </div>
        </div>
    </div>
</div>
@endsection
