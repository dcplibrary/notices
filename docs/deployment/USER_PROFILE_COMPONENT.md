# User Profile Component (Microsoft 365 Style)

This document explains the user profile avatar component with initials/photo and dropdown menu in the dashboard header.

## Implementation Overview

The Microsoft 365-style user profile component:
- Shows user initials in a circle (first letter of `givenName` + first letter of `surname` from AD)
- Falls back to first 2 letters of name if AD attributes unavailable
- Displays profile photo if available (placeholder - not yet implemented)
- Opens dropdown menu with "Logged in as {givenName} {surname}" and logout
- Positioned at the right end of header, left of the help icon

**Status:** ✅ **Implemented** - Initials and dropdown working. Profile photo support is a placeholder for future enhancement.

---

## Prerequisites

### Required Active Directory Attributes

Your Entra SSO (Azure AD) configuration must return these user attributes:

1. **`givenName`** (First name) - Required for initials
2. **`surname`** (Last name) - Required for initials
3. **`name`** (Full name) - Fallback if givenName/surname unavailable
4. **`email`** or **`mail`** - For user identification
5. **`profile_photo`** (Optional) - For future profile photo implementation

### Verify Entra SSO Package Configuration

Ensure your `dcplibrary/entra-sso` package returns `givenName` and `surname` from Azure AD. The user model should expose these as properties (e.g., `Auth::user()->givenName`).

---

## Current Implementation (v1.0)

The component is already implemented in `resources/views/layouts/app.blade.php`. This section documents how it works.

### How It Works

**Desktop Header:**
```blade
<!-- Right side: User Profile & Help -->
<div class="hidden sm:flex sm:items-center sm:space-x-4">
    <!-- Help Icon -->
    <a href="/notices/help"
       class="text-gray-600 hover:text-gray-900 inline-flex items-center transition-colors"
       title="Help & User Guide"
       aria-label="Help and User Guide"
       target="_blank">
        <svg><!-- question mark icon --></svg>
    </a>

    <!-- User Profile Dropdown -->
    @if(Auth::check())
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open"
                type="button"
                class="flex items-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 rounded-full"
                aria-label="User menu"
                aria-expanded="false">
            @if(Auth::user()->profile_photo ?? false)
                <img src="{{ Auth::user()->profile_photo }}"
                     alt="{{ Auth::user()->name }}"
                     class="w-10 h-10 rounded-full object-cover border-2 border-gray-200 hover:border-gray-300 transition-colors">
            @else
                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold text-sm border-2 border-blue-600 hover:border-blue-700 transition-colors">
                    @php
                        $givenName = Auth::user()->givenName ?? '';
                        $surname = Auth::user()->surname ?? '';

                        if (!empty($givenName) && !empty($surname)) {
                            $initials = strtoupper(substr($givenName, 0, 1) . substr($surname, 0, 1));
                        } else {
                            $initials = strtoupper(substr(Auth::user()->name ?? 'U', 0, 2));
                        }
                    @endphp
                    {{ $initials }}
                </div>
            @endif
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open"
             @click.away="open = false"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg py-1 z-50 border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-sm text-gray-500">Logged in as</p>
                <p class="text-sm font-medium text-gray-900 truncate">
                    @php
                        if (!empty($givenName) && !empty($surname)) {
                            $displayName = trim($givenName . ' ' . $surname);
                        } else {
                            $displayName = Auth::user()->name ?? Auth::user()->email ?? 'User';
                        }
                    @endphp
                    {{ $displayName }}
                </p>
            </div>
            <a href="/logout"
               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                Logout
            </a>
        </div>
    </div>
    <form id="logout-form" action="/logout" method="POST" style="display: none;">
        @csrf
    </form>
    @endif
</div>
```

**Mobile Menu:**
The user info and logout are also displayed in the mobile menu at the bottom, after the navigation links.

### Initials Logic

```php
$givenName = Auth::user()->givenName ?? '';
$surname = Auth::user()->surname ?? '';

if (!empty($givenName) && !empty($surname)) {
    // Use first letter of givenName + first letter of surname
    $initials = strtoupper(substr($givenName, 0, 1) . substr($surname, 0, 1));
} else {
    // Fallback: first 2 letters of name
    $initials = strtoupper(substr(Auth::user()->name ?? 'U', 0, 2));
}
```

**Key requirement:** Both `givenName` AND `surname` must exist. If either is missing, it falls back to using the `name` attribute. This prevents showing incorrect initials like "BR" when only `givenName` exists.

---

## Future Enhancement: Profile Photos

The component has a placeholder for profile photos but this feature is not yet implemented. Below is the implementation plan for when needed.

### Add to `.env` (For Future Photo Support)

```env
# Optional: Profile Photo Settings (not yet implemented)
AZURE_AD_FETCH_PHOTO=true              # Enable fetching profile photos
AZURE_AD_PHOTO_SIZE=48x48              # Photo size (48x48, 96x96, 240x240, 432x432, 648x648)
AZURE_AD_PHOTO_CACHE_TTL=3600          # Cache photos for 1 hour (in seconds)
```

### Implementation Steps for Photo Support

When implementing profile photos:

1. **Add database migration** (optional - only if caching photos):
   ```php
   Schema::table('users', function (Blueprint $table) {
       $table->string('profile_photo')->nullable()->after('email');
       $table->timestamp('profile_photo_updated_at')->nullable();
   });
   ```

2. **Fetch photo during login callback**:
   See "Fetching Profile Photos from Azure AD" section below.

3. **Update user model** to include `profile_photo` in fillable fields.

The view template already checks for `Auth::user()->profile_photo` and will automatically display it when available.

---

## Fetching Profile Photos from Azure AD

### Option 1: Fetch Photo During Login (Recommended)

Update your Entra SSO login callback to fetch and cache the profile photo:

```php
// In your EntraController or wherever you handle the OAuth callback

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

public function handleCallback()
{
    // ... existing OAuth handling ...

    // After user is authenticated and user object is created/updated:
    $user = auth()->user();

    // Fetch profile photo from Microsoft Graph API
    if (config('entra.profile.fetch_photo', true)) {
        $this->fetchUserPhoto($user, $accessToken);
    }

    // ... rest of callback logic ...
}

protected function fetchUserPhoto($user, $accessToken)
{
    try {
        $photoSize = config('entra.profile.photo_size', '48x48');

        $response = Http::withToken($accessToken)
            ->get("https://graph.microsoft.com/v1.0/me/photos/{$photoSize}/\$value");

        if ($response->successful()) {
            // Save photo to storage
            $filename = "profile-photos/{$user->id}.jpg";
            Storage::disk('public')->put($filename, $response->body());

            // Update user record with photo URL
            $user->update([
                'profile_photo' => Storage::url($filename),
                'profile_photo_updated_at' => now(),
            ]);
        }
    } catch (\Exception $e) {
        // Silently fail - initials will be shown instead
        \Log::warning("Failed to fetch profile photo for user {$user->id}: " . $e->getMessage());
    }
}
```

### Option 2: Lazy Load Photo (On-Demand)

Create a route that fetches the photo only when needed:

```php
// routes/web.php
Route::get('/user/photo', [UserController::class, 'photo'])
    ->middleware('auth')
    ->name('user.photo');

// UserController.php
public function photo(Request $request)
{
    $user = $request->user();

    // Check cache first
    $cacheKey = "user_photo_{$user->id}";
    $cacheTtl = config('entra.profile.photo_cache_ttl', 3600);

    return Cache::remember($cacheKey, $cacheTtl, function () use ($user) {
        // Fetch from Microsoft Graph
        $token = $this->getGraphAccessToken(); // Your method to get token

        $response = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/users/{$user->email}/photo/\$value");

        if ($response->successful()) {
            return response($response->body())
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=3600');
        }

        // Return placeholder if no photo
        abort(404);
    });
}
```

Then use in blade:

```blade
<img src="{{ route('user.photo') }}"
     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
     class="w-10 h-10 rounded-full">
<div class="w-10 h-10 rounded-full bg-blue-600 hidden">
    <!-- initials fallback -->
</div>
```

---

## Troubleshooting

### Initials Not Showing Correctly

**Problem:** Avatar shows "BR" instead of "BL" (first name + last name)

**Solution:** Verify that BOTH `givenName` AND `surname` are available from AD:

```php
// In tinker or debug route:
dd([
    'givenName' => Auth::user()->givenName,
    'surname' => Auth::user()->surname,
    'name' => Auth::user()->name,
]);
```

If either is missing, the avatar will fall back to using the first 2 letters of `name`.

**Fix:** Ensure your Entra SSO package retrieves and maps both attributes from Azure AD.

### Initials Not Appearing at All

1. **Check authentication**: User must be logged in (`Auth::check()`)
2. **View page source**: Verify the component renders in HTML
3. **Check for errors**: Open browser console (F12) for JavaScript errors

### Dropdown Not Working

1. **Alpine.js not loaded**: Verify Alpine.js is loaded in page source
   ```html
   <!-- Should see this in <head> -->
   <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
   ```

2. **Click-away not working**: Test clicking outside dropdown to close it
3. **JavaScript errors**: Open browser console to check for errors

### Profile Photos (Future Feature)

Profile photos are not yet implemented. The `@if(Auth::user()->profile_photo ?? false)` check is a placeholder for future enhancement.

---

## Implementation Checklist

Current implementation (v1.0):

- [x] User initials in circular avatar
- [x] First letter of givenName + first letter of surname
- [x] Fallback to first 2 letters of name if AD attributes missing
- [x] Dropdown menu with "Logged in as {name}"
- [x] Logout button in dropdown
- [x] Mobile menu support
- [x] Alpine.js dropdown functionality
- [x] Tailwind CSS styling
- [ ] Profile photo from Azure AD (planned future enhancement)
- [ ] Database caching of photos (planned future enhancement)

---

## Code Reference

**File:** `resources/views/layouts/app.blade.php`
**Lines:** 62-149 (desktop), 200-228 (mobile)

---

## Summary

**Current Status (v1.0):**

✅ **Implemented:**
- User initials in circular avatar (givenName + surname from AD)
- Dropdown menu with user name and logout
- Mobile menu support
- Alpine.js dropdown functionality
- Tailwind CSS styling
- Help icon integration

🔲 **Future Enhancements:**
- Profile photo fetching from Microsoft Graph API
- Database caching of profile photos
- Additional user info in dropdown (job title, department)

**File Location:** `resources/views/layouts/app.blade.php` (lines 62-149, 200-228)

**Dependencies:**
- Alpine.js 3.x (already loaded)
- Tailwind CSS (already configured)
- Azure AD attributes: `givenName`, `surname`, `name` (via Entra SSO package)
