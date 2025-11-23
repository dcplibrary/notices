@extends('notices::layouts.app')

@section('content')
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Notification Setting</h1>

        @isset($setting)
            <div class="bg-white shadow rounded p-4">
                <p><strong>Key:</strong> {{ $setting->full_key }}</p>
                <p><strong>Value:</strong> {{ $setting->getMaskedValue() }}</p>
                <p><strong>Description:</strong> {{ $setting->description }}</p>
            </div>
        @else
            <p>No setting data available.</p>
        @endisset
    </div>
@endsection
