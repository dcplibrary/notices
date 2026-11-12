@props([
    'variant' => 'sms_voice', // 'sms_voice' or 'email'
    'title',
    'description' => null,
    'counts' => ['patrons' => 0, 'contacts' => 0],
    'contacts' => null, // Illuminate\Support\Collection of FailedDeliveryString
    'contactLabel' => null, // e.g. "phone(s)" or "email(s)"; defaults based on variant
    'emptyMessage' => null,
    'viewMoreText' => null,
])

@php
    $contacts = $contacts ?? collect();

    $isSmsVoice = $variant === 'sms_voice';

    // Styling classes based on variant
    // Outer card + icon styling – align sms_voice variant with "Patrons with Failed Contact Information" layout
    $outerClasses = $isSmsVoice
        ? 'bg-purple-50 dark:bg-purple-900/20 rounded-2xl border border-purple-200 dark:border-purple-700 shadow-sm overflow-hidden'
        : 'bg-gradient-to-r from-blue-50 to-emerald-50 dark:from-blue-900/20 dark:to-emerald-900/20 rounded-lg border-2 border-blue-300 dark:border-blue-700 shadow-md';

    $iconWrapperClasses = $isSmsVoice
        ? 'flex-shrink-0 w-10 h-10 rounded-full bg-purple-500/10 dark:bg-purple-900/60 flex items-center justify-center'
        : 'flex-shrink-0 p-3 bg-blue-100 dark:bg-blue-900/40 rounded-lg';

    $iconColorClasses = $isSmsVoice
        ? 'w-5 h-5 text-purple-500 dark:text-purple-300'
        : 'w-6 h-6 text-dcpl-blue dark:text-sky-300';

    $summaryPatronColor = $isSmsVoice
        ? 'font-semibold text-purple-700 dark:text-purple-300'
        : 'font-semibold text-blue-700 dark:text-blue-300';

    $rowBorderClasses = $isSmsVoice
        ? 'border-purple-200 dark:border-purple-800 hover:border-purple-400 dark:hover:border-purple-500'
        : 'border-blue-200 dark:border-blue-800 hover:border-blue-400 dark:hover:border-blue-500';

    $viewMoreLinkClasses = $isSmsVoice
        ? 'inline-flex items-center gap-1 text-purple-700 dark:text-purple-300 hover:underline'
        : 'inline-flex items-center gap-1 text-blue-700 dark:text-blue-300 hover:underline';

    $contactLabel = $contactLabel
        ?? ($isSmsVoice ? 'phone(s)' : 'email(s)');

    $emptyMessage = $emptyMessage
        ?? ($isSmsVoice
            ? 'No patrons with failed SMS/voice contacts in this period.'
            : 'No patrons with failed email contacts in this period.'
        );

    $viewMoreText = $viewMoreText
        ?? ($isSmsVoice
            ? 'View more patrons with failed SMS/voice contacts'
            : 'View more patrons with failed email contacts'
        );

    $totalContacts = (int)($counts['contacts'] ?? 0);
    $showViewMore = $totalContacts > $contacts->count();
@endphp

@if($isSmsVoice)
    @php
        // Use override-aware delivery method labels for filter pills.
        $lookupLabelService = app(\Dcplibrary\Notices\Services\LookupLabelService::class);
        $emailLabel = $lookupLabelService->getDeliveryLabel(2);
        $voiceLabel = $lookupLabelService->getDeliveryLabel(3);
        $smsLabel   = $lookupLabelService->getDeliveryLabel(8);
    @endphp
    <!-- SMS/Voice failed contacts: full-width failed-contact list layout with filters -->
    <div x-data="{ filter: 'all', showResolved: false }" class="{{ $outerClasses }}">
        <!-- Header + filters -->
        <div class="px-6 py-4 border-b border-purple-100/80 dark:border-purple-800/60 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="{{ $iconWrapperClasses }}">
                    <!-- Alert icon -->
                    <svg class="{{ $iconColorClasses }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86L1.82 18a1 1 0 00.86 1.5h18.64a1 1 0 00.86-1.5L13.71 3.86a1 1 0 00-1.72 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h3>
                </div>
            </div>

            <!-- Filter buttons -->
            <div class="flex items-center gap-2 text-xs">
                <button
                    type="button"
                    @click="filter = 'all'"
:class="filter === 'all'
                        ? 'bg-purple-600 text-white shadow-sm'
                        : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                    class="px-4 py-1.5 rounded-full font-semibold"
                >
                    All
                </button>
                <button
                    type="button"
                    @click="filter = 'email'"
:class="filter === 'email'
                        ? 'bg-purple-600 text-white shadow-sm'
                        : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                    class="px-4 py-1.5 rounded-full font-semibold"
                >
                    {{ $emailLabel }} Issues
                </button>
                <button
                    type="button"
                    @click="filter = 'voice'"
:class="filter === 'voice'
                        ? 'bg-purple-600 text-white shadow-sm'
                        : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                    class="px-4 py-1.5 rounded-full font-semibold"
                >
                    {{ $voiceLabel }} Issues
                </button>
                <button
                    type="button"
                    @click="filter = 'sms'"
:class="filter === 'sms'
                        ? 'bg-purple-600 text-white shadow-sm'
                        : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                    class="px-4 py-1.5 rounded-full font-semibold"
                >
                    {{ $smsLabel }} Issues
                </button>
                <button
                    type="button"
                    @click="showResolved = !showResolved"
                    :class="showResolved
                        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-100 dark:text-slate-900'
                        : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'"
                    class="px-4 py-1.5 rounded-full font-semibold"
                >
                    Show Resolved
                </button>
            </div>
        </div>

        @if($contacts->isEmpty())
            <div class="px-6 py-6 text-xs text-slate-600 dark:text-slate-400">
                {{ $emptyMessage }}
            </div>
        @else
            <!-- Header row -->
            <div class="bg-slate-50 dark:bg-slate-800/40">
                <div class="px-6 py-3 grid grid-cols-[7.5rem_minmax(10rem,1fr)_minmax(10rem,1.15fr)_5.25rem_minmax(14rem,2fr)_7.5rem] gap-3 text-[11px] font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wide">
                    <div class="truncate">Barcode</div>
                    <div>Name</div>
                    <div>Phone / Email</div>
                    <div class="text-center whitespace-nowrap">Method</div>
                    <div>Issue Type</div>
                    <div class="text-right">Last Attempt</div>
                </div>
            </div>

            <!-- Rows -->
            <div class="bg-white dark:bg-slate-900/40 divide-y divide-purple-100 dark:divide-purple-900/40">
                @foreach($contacts as $failed)
                    @php
                        $patron   = $failed->patron ?? null;
                        $deliveryOptionId = (int) ($failed->delivery_option_id ?? 0);
                        $isSms   = $deliveryOptionId === 8;
                        $isVoice = $deliveryOptionId === 3;
                        $isPhone  = ($failed->string_type ?? null) === 'phone' || in_array($deliveryOptionId, [3, 8], true);
                        // Filter key for pills: email / voice / sms
                        $issueType = $isSms ? 'sms' : ($isVoice ? 'voice' : 'email');
                        $emailValue = !$isPhone ? $failed->delivery_string : ($patron->email ?? null);
                        $phoneValue = $isPhone ? $failed->delivery_string : ($patron->phone ?? null);
                        
                        // Check if this is a mismatched preference issue
                        $isMismatched = ($failed->issue_type ?? null) === 'mismatched_preference';

                        // Derive ui_messages key from failure context
                        if ($isMismatched) {
                            $messageKey = 'mismatched_preference';
                        } elseif (!$isPhone) {
                            // Email failures – categorize based on failure details/source
                            $reason = strtolower($failed->failure_details ?? $failed->failure_source ?? '');
                            if (str_contains($reason, 'invalid')) {
                                $messageKey = 'email_invalid_address';
                            } elseif (str_contains($reason, 'bounce') || str_contains($reason, 'bounced')) {
                                $messageKey = 'email_bounce';
                            } else {
                                $messageKey = 'email_delivery_failure';
                            }
                        } else {
                            // SMS/Voice (Shoutbomb) failures – mirror mapping used in notification-detail-modal
                            $failureType = $failed->failure_source ?? '';
                            $messageKey = match($failureType) {
                                'Opted Out'            => 'sms_opt_out',
                                'Invalid Phone Number' => 'sms_invalid_phone',
                                'Landline'            => 'sms_landline',
                                'Carrier Issue'       => 'sms_carrier_issue',
                                default               => 'sms_delivery_failure',
                            };
                        }

                        // Resolve label: override -> default config -> prettified key
                        $labelsConfig = config('notices.ui_labels', []);
                        $defaultLabel = $labelsConfig[$messageKey] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $messageKey));
                        $override    = \Dcplibrary\Notices\Models\UiMessage::where('key', $messageKey)->first();
                        $issueLabel  = $override->label ?? $defaultLabel;
                        
                        // For mismatched preference, use the issue details as the label if available
                        if ($isMismatched && !empty($failed->failure_details)) {
                            // Extract a short description from issue_details
                            $issueLabel = 'Mismatched Preference';
                        }
                    @endphp
                    <div
                        class="px-6 py-3 grid grid-cols-[7.5rem_minmax(10rem,1fr)_minmax(10rem,1.15fr)_5.25rem_minmax(14rem,2fr)_7.5rem] gap-3 items-center text-xs text-slate-700 dark:text-slate-200 hover:bg-purple-50/80 dark:hover:bg-purple-900/40 transition-colors cursor-pointer"
                        x-show="(filter === 'all' || filter === '{{ $issueType }}') && (showResolved || !{{ (int)($failed->is_resolved ?? 0) }})"
                        @click="$dispatch('failed-contact-open', { id: {{ json_encode($failed->id) }} })"
                    >
                        <!-- Barcode -->
                        <div class="font-mono text-[11px] text-slate-600 dark:text-slate-400 truncate">
                            {{ $patron->patron_barcode ?? $failed->example_patron_barcode ?? '—' }}
                        </div>

                        <!-- Name -->
                        <div class="flex flex-col gap-0.5">
                            @if($patron && ($patron->patron_last_name || $patron->patron_first_name))
                                <div class="font-medium text-slate-900 dark:text-white">
                                    {{ $patron->patron_last_name ?? '' }}@if($patron->patron_last_name && $patron->patron_first_name), @endif{{ $patron->patron_first_name ?? '' }}
                                </div>
                            @else
                                <div class="font-medium text-slate-900 dark:text-white">Unknown patron</div>
                            @endif
                            @if($patron && $patron->patron_id)
                                <a
                                    href="https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/{{ $patron->patron_id }}/record"
                                    target="_blank"
                                    class="text-[10px] text-dcpl-blue dark:text-sky-300 hover:underline inline-flex items-center gap-1"
                                    @click.stop
                                >
                                    View in Polaris
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                            @endif
                        </div>

                        <!-- Phone / Email (problematic contact) -->
                        <div class="text-xs text-slate-800 dark:text-slate-100 break-all">
                            @if($isPhone)
                                {{ $phoneValue ?? '—' }}
                            @else
                                {{ $emailValue ?? '—' }}
                            @endif
                        </div>

                        <!-- Delivery Method (Icon) -->
                        <div class="flex justify-center">
                            @if((int) $failed->delivery_option_id === 1)
                                <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Mail" aria-label="Mail">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            @elseif((int) $failed->delivery_option_id === 2)
                                <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Email" aria-label="Email">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                </svg>
                            @elseif((int) $failed->delivery_option_id === 3)
                                <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Voice" aria-label="Voice">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            @elseif((int) $failed->delivery_option_id === 8)
                                <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="SMS" aria-label="SMS">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                </svg>
                            @else
                                <span class="text-slate-500 dark:text-slate-500 italic" title="Unknown" aria-label="Unknown">—</span>
                            @endif
                        </div>

                        <!-- Issue Type (UI message override label) -->
                        <div class="text-xs font-medium">
                            {{ $issueLabel }}
                        </div>

                        <!-- Last Attempt -->
                        <div class="text-xs text-slate-700 dark:text-slate-200 text-right">
                            @if($failed->last_failed_at)
                                {{ $failed->last_failed_at->format('Y-m-d H:i') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($showViewMore)
                <div class="px-6 py-3 border-t border-purple-100 dark:border-purple-900/40 bg-purple-50/40 dark:bg-purple-900/10 text-sm flex justify-between items-center">
                    <div class="text-[11px] text-slate-600 dark:text-slate-400">View all patron notification issues</div>
                    <a href="{{ route('notices.issues', ['type' => 'failed_contacts']) }}" class="{{ $viewMoreLinkClasses }}">
                        View More
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            @endif
        @endif
    </div>
@else
    <!-- Email-only failed contacts: keep original compact card layout -->
    <div class="{{ $outerClasses }}">
        <div class="p-6">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-start gap-3">
                    <div class="{{ $iconWrapperClasses }}">
                        <!-- Email / contact icon -->
                        <svg class="{{ $iconColorClasses }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h3>
                        @if($description)
                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                                {{ $description }}
                            </p>
                        @endif
                    </div>
                </div>
                <div class="text-right text-xs text-slate-600 dark:text-slate-400">
                    <p class="{{ $summaryPatronColor }}">
                        {{ number_format($counts['patrons'] ?? 0) }} patron(s)
                    </p>
                    <p class="text-slate-500 dark:text-slate-500">
                        {{ number_format($counts['contacts'] ?? 0) }} {{ $contactLabel }}
                    </p>
                </div>
            </div>

            @if($contacts->isEmpty())
                <p class="text-xs text-slate-600 dark:text-slate-400">
                    {{ $emptyMessage }}
                </p>
            @else
                <ul class="space-y-2 mb-3">
                    @foreach($contacts as $failed)
                        @php
                            $patron = $failed->patron ?? null;
                        @endphp
                        <li
                            class="py-3 px-3 bg-white/70 dark:bg-slate-900/40 rounded-lg border {{ $rowBorderClasses }} hover:shadow-sm transition-all cursor-pointer"
                            @click="$dispatch('failed-contact-open', { id: {{ $failed->id }} })"
                        >
                            <div class="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400">
                                <div class="flex flex-wrap gap-6">
                                    <div class="w-28">Email</div>
                                    <div class="w-32">Barcode</div>
                                    <div class="flex-1 min-w-[8rem]">Name</div>
                                </div>
                                <div class="flex flex-wrap gap-6 text-slate-900 dark:text-white">
                                    <div class="w-28 font-mono text-sm break-all">{{ $failed->delivery_string }}</div>
                                    <div class="w-32 font-mono text-sm">
                                        @if($patron && $patron->patron_barcode)
                                            {{ $patron->patron_barcode }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-[8rem] text-sm">
                                        @if($patron && ($patron->patron_last_name || $patron->patron_first_name))
                                            {{ $patron->patron_last_name ?? '' }}@if($patron->patron_last_name && $patron->patron_first_name), @endif{{ $patron->patron_first_name ?? '' }}
                                        @else
                                            Unknown patron
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
