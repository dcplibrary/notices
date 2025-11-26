# Adding Help Icon to Dashboard Header

This document explains how to add a help icon to the dashboard header that links to the User Guide.

## Implementation Instructions

### 1. Locate the Header View

The header/navigation is typically in:
- `resources/views/layouts/app.blade.php`
- `resources/views/notices/layouts/app.blade.php`

### 2. Add Help Icon

Add this code to the header navigation. It should be positioned at the far right of the header, after the user profile avatar component (see [User Profile Component](../deployment/USER_PROFILE_COMPONENT.md) for implementing the avatar):

```html
<!-- Help Icon -->
<a href="{{ url('/notices/help') }}"
   class="text-gray-600 hover:text-gray-900 inline-flex items-center"
   title="Help & User Guide"
   target="_blank">
    <!-- Question mark circle icon (monochromatic line style) -->
    <svg xmlns="http://www.w3.org/2000/svg"
         fill="none"
         viewBox="0 0 24 24"
         stroke-width="1.5"
         stroke="currentColor"
         class="w-6 h-6">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
    </svg>
    <span class="ml-1 hidden md:inline">Help</span>
</a>
```

**Icon Options:**

Option 1 - Question mark in circle (recommended):
```svg
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
</svg>
```

Option 2 - Simple question mark:
```svg
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75m0 3v.375m0-.375h.008v.008H12v-.008z" />
</svg>
```

Option 3 - Information icon (alternative):
```svg
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
</svg>
```

### 3. Create Help Route

Add this route to `routes/web.php`:

```php
Route::prefix('notices')->name('notices.')->group(function () {
    // ... existing routes ...

    // Help page
    Route::get('/help', function () {
        return view('notices::help.index');
    })->name('help');
});
```

### 4. Create Help View

Create `resources/views/notices/help/index.blade.php`:

```blade
@extends('notices::layouts.app')

@section('title', 'User Guide')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow-sm rounded-lg p-8">
            {!! \Illuminate\Support\Facades\File::get(base_path('vendor/dcplibrary/notices/docs/help/USER_GUIDE.md')) !!}
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('notices.dashboard') }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
```

**Or** use a markdown parser for better formatting:

```blade
@extends('notices::layouts.app')

@section('title', 'User Guide')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto prose prose-lg">
        {!! \Parsedown::instance()->text(
            \Illuminate\Support\Facades\File::get(base_path('vendor/dcplibrary/notices/docs/help/USER_GUIDE.md'))
        ) !!}

        <div class="mt-8 text-center not-prose">
            <a href="{{ route('notices.dashboard') }}"
               class="text-blue-600 hover:text-blue-800 font-medium">
                ← Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
```

### 5. Styling Recommendations

**Header placement:** Place the help icon in the top-right corner of the header, near the user menu or login/logout buttons.

**Icon size:** `w-6 h-6` (24x24px) matches standard header icon sizes

**Color:** Use neutral gray that matches your existing header icons:
- Default: `text-gray-600`
- Hover: `hover:text-gray-900`
- Active: `text-blue-600` (optional)

**Responsive:** Hide the "Help" text on mobile (`hidden md:inline`) to save space

### 6. Accessibility

Make sure to include:
- `title` attribute for tooltip
- `aria-label` for screen readers
- Color contrast meets WCAG AA standards

```html
<a href="{{ url('/notices/help') }}"
   class="text-gray-600 hover:text-gray-900"
   title="Help & User Guide"
   aria-label="Help and User Guide"
   target="_blank">
    <!-- icon -->
</a>
```

## Alternative: Dropdown Menu

If you prefer a dropdown with multiple help options:

```html
<div class="relative" x-data="{ open: false }">
    <button @click="open = !open"
            class="text-gray-600 hover:text-gray-900"
            aria-label="Help menu">
        <!-- Question mark icon -->
    </button>

    <div x-show="open"
         @click.away="open = false"
         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg">
        <a href="{{ url('/notices/help') }}"
           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
           target="_blank">
            User Guide
        </a>
        <a href="{{ url('docs/INDEX.md') }}"
           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
           target="_blank">
            Technical Documentation
        </a>
    </div>
</div>
```

## Testing

After implementation:

1. ✅ Icon appears in header
2. ✅ Icon matches existing header icon style
3. ✅ Clicking opens help page in new tab
4. ✅ Help page content displays correctly
5. ✅ Markdown formatting renders properly
6. ✅ "Back to Dashboard" link works
7. ✅ Responsive design works on mobile

## Notes

- The help icon should open in a **new tab** (`target="_blank"`) so users don't lose their place in the dashboard
- Consider adding a markdown parsing library (like `league/commonmark`) for better rendering of the USER_GUIDE.md file
- The help page should be accessible without authentication (or require same auth as dashboard)
