@extends('notices::layouts.app')

@section('title', 'User Guide')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow-sm rounded-lg p-8 prose prose-lg max-w-none">
            {!! \Illuminate\Support\Str::markdown(
                \Illuminate\Support\Facades\File::get(base_path('vendor/dcplibrary/notices/docs/help/USER_GUIDE.md'))
            ) !!}
        </div>

        <div class="mt-8 text-center">
            <a href="/notices" class="text-blue-600 hover:text-blue-800 font-medium">
                ← Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
