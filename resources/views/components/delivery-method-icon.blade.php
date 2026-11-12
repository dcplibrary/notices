{{--
    Delivery Method Icon Component
    
    Renders an icon for a delivery option based on its icon_name attribute.
    The icon_name is determined by the DeliveryOption model based on the
    delivery_option name from the database (not hardcoded IDs).
    
    Usage: <x-notices::delivery-method-icon :deliveryOption="$notification->deliveryOption" />
    
    Icon names (from DeliveryOption model constants):
    - 'mail'    = Physical mail (envelope icon)
    - 'email'   = Email (@ symbol icon)
    - 'phone'   = Voice/Phone (phone icon)
    - 'sms'     = SMS/Text messaging (chat bubble icon)
    - 'unknown' = Fallback (shows label or ?)
--}}
@props(['deliveryOption' => null])

@php
    use Dcplibrary\Notices\Models\DeliveryOption;
    
    $option = $deliveryOption;
    $label = $option?->display_label ?? $option?->delivery_option ?? 'Unknown';
    $iconName = $option?->icon_name ?? DeliveryOption::ICON_UNKNOWN;
@endphp

@if($option)
    @switch($iconName)
        @case(DeliveryOption::ICON_MAIL)
            {{-- Physical Mail --}}
            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-label="{{ $label }}" title="{{ $label }}">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            @break
        @case(DeliveryOption::ICON_EMAIL)
            {{-- Email --}}
            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-label="{{ $label }}" title="{{ $label }}">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
            </svg>
            @break
        @case(DeliveryOption::ICON_PHONE)
            {{-- Phone/Voice --}}
            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-label="{{ $label }}" title="{{ $label }}">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
            </svg>
            @break
        @case(DeliveryOption::ICON_SMS)
            {{-- SMS/Text --}}
            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-label="{{ $label }}" title="{{ $label }}">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
            </svg>
            @break
        @default
            {{-- Unknown method - show label --}}
            <span class="text-slate-500 dark:text-slate-500 text-xs" title="{{ $label }}">{{ $label }}</span>
    @endswitch
@else
    <span class="text-slate-500 dark:text-slate-500 italic" aria-label="Unknown" title="Unknown">?</span>
@endif
