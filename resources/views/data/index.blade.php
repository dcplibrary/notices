@extends('notices::layouts.app')

@section('title', 'Data Browser')

@section('body')
<div class="notices-staff-page pb-10">
<div class="max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="mb-8 border-l-4 border-dcpl-blue pl-4">
        <h1 class="font-outfit text-3xl font-semibold tracking-tight text-dcpl-text">Data Browser</h1>
        <p class="mt-1 text-sm text-gray-600">
            Browse imported data from Polaris exports and LX Starter reports
        </p>
    </div>

    <!-- Tabs -->
    <x-dcpl::sub-tab-nav label="Data section">
        <x-dcpl::sub-tab label="Overview"
                         href="{{ route('notices.data.search') }}"
                         :active="request()->routeIs('notices.data.search')" />
        <x-dcpl::sub-tab label="Report Emails"
                         href="{{ route('notices.data.report-emails') }}"
                         :active="request()->routeIs('notices.data.report-emails*')" />
        <x-dcpl::sub-tab label="Export Files"
                         href="{{ route('notices.data.export-files') }}"
                         :active="request()->routeIs('notices.data.export-files*')" />
    </x-dcpl::sub-tab-nav>

    <!-- Tab Content -->
    @yield('tab-content')
</div>
</div>
@endsection
