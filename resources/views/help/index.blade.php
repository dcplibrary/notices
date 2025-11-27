@extends('notices::layouts.app')

@section('title', 'User Guide')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow-sm rounded-lg p-8 prose prose-lg max-w-none" id="markdown-content">
            <div class="text-center py-8">
                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-gray-600">Loading user guide...</p>
            </div>
        </div>

        <div class="mt-8 text-center">
            <a href="/notices" class="text-blue-600 hover:text-blue-800 font-medium">
                ← Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js"></script>
<script>
    // Fetch and render the markdown file
    fetch('/notices/help/content')
        .then(response => response.text())
        .then(markdown => {
            const html = marked.parse(markdown);
            document.getElementById('markdown-content').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('markdown-content').innerHTML = `
                <div class="text-center py-8">
                    <div class="text-red-600 mb-4">
                        <svg class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Failed to Load User Guide</h3>
                    <p class="mt-2 text-sm text-gray-600">${error.message}</p>
                </div>
            `;
        });
</script>
@endpush
