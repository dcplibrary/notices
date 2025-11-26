# User Profile Component (Microsoft 365 Style)

This document explains how to add a user profile avatar with initials/photo and dropdown menu to the dashboard header.

## Implementation Overview

Add a Microsoft 365-style user profile component that:
- Shows user initials in a circle (extracted from AD `givenName` and `surname`)
- Displays profile photo if available from AD
- Opens dropdown menu with "Logged in as..." and logout option
- Positioned at the right end of header, left of the help icon

---

## Prerequisites

### Required Active Directory Attributes

Your Entra SSO (Azure AD) configuration must return these user attributes:

1. **`givenName`** (First name)
2. **`surname`** (Last name)
3. **`mail`** or **`userPrincipalName`** (Email)
4. **`photo`** (Profile picture - optional)

### Verify Entra SSO Package Configuration

Check your `dcplibrary/entra-sso` package configuration to ensure it retrieves these attributes from Azure AD.

---

## Environment Configuration

### Add to `.env` (if not already present)

```env
# Azure AD / Entra ID Configuration
AZURE_AD_TENANT_ID=your-tenant-id
AZURE_AD_CLIENT_ID=your-client-id
AZURE_AD_CLIENT_SECRET=your-client-secret
AZURE_AD_REDIRECT_URI=https://yourapp.com/entra/callback

# Optional: Profile Photo Settings
AZURE_AD_FETCH_PHOTO=true              # Enable fetching profile photos
AZURE_AD_PHOTO_SIZE=48x48              # Photo size (48x48, 96x96, 240x240, 432x432, 648x648)
AZURE_AD_PHOTO_CACHE_TTL=3600          # Cache photos for 1 hour (in seconds)
```

### Update `config/entra.php` (if exists)

Add these settings to your Entra config file:

```php
return [
    // ... existing config ...

    // User profile settings
    'profile' => [
        'fetch_photo' => env('AZURE_AD_FETCH_PHOTO', true),
        'photo_size' => env('AZURE_AD_PHOTO_SIZE', '48x48'),
        'photo_cache_ttl' => env('AZURE_AD_PHOTO_CACHE_TTL', 3600),

        // Required user attributes from Azure AD
        'required_attributes' => [
            'givenName',
            'surname',
            'mail',
            'userPrincipalName',
        ],

        // Optional attributes
        'optional_attributes' => [
            'photo',
            'jobTitle',
            'department',
        ],
    ],
];
```

---

## Implementation Steps

### Step 1: Update Header Layout

Locate your header file (usually `resources/views/layouts/app.blade.php` or `resources/views/notices/layouts/app.blade.php`).

Add this code to the header, before the help icon:

```blade
<div class="flex items-center gap-4">
    <!-- User Profile Dropdown -->
    <div class="relative" x-data="{ open: false }">
        <!-- Avatar Button -->
        <button @click="open = !open"
                @click.away="open = false"
                class="flex items-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 rounded-full"
                aria-label="User menu">
            @if(auth()->user()->profile_photo ?? false)
                <!-- Profile Photo -->
                <img src="{{ auth()->user()->profile_photo }}"
                     alt="{{ auth()->user()->name }}"
                     class="w-10 h-10 rounded-full object-cover border-2 border-gray-300">
            @else
                <!-- Initials Circle -->
                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold text-sm border-2 border-gray-300">
                    {{ strtoupper(substr(auth()->user()->givenName ?? auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(auth()->user()->surname ?? '', 0, 1)) }}
                </div>
            @endif
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="transform opacity-0 scale-95"
             x-transition:enter-end="transform opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="transform opacity-100 scale-100"
             x-transition:leave-end="transform opacity-0 scale-95"
             class="absolute right-0 mt-2 w-64 bg-white rounded-md shadow-lg py-1 ring-1 ring-black ring-opacity-5 z-50"
             style="display: none;">

            <!-- User Info -->
            <div class="px-4 py-3 border-b border-gray-200">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Logged in as</p>
                <p class="text-sm font-medium text-gray-900 mt-1">
                    {{ auth()->user()->givenName ?? auth()->user()->name }} {{ auth()->user()->surname ?? '' }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ auth()->user()->email }}
                </p>
                @if(auth()->user()->jobTitle ?? false)
                    <p class="text-xs text-gray-500">
                        {{ auth()->user()->jobTitle }}
                    </p>
                @endif
            </div>

            <!-- Logout -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 inline-block mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    Sign out
                </button>
            </form>
        </div>
    </div>

    <!-- Help Icon (existing) -->
    <a href="{{ url('/notices/help') }}"
       class="text-gray-600 hover:text-gray-900 inline-flex items-center"
       title="Help & User Guide"
       target="_blank">
        <!-- question mark icon -->
    </a>
</div>
```

### Step 2: Ensure Alpine.js is Loaded

The dropdown requires Alpine.js for the click-away functionality. Add this to your layout's `<head>` if not already present:

```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

Or use the version bundled with Laravel/Livewire if available.

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

## Database Migration (if storing photos)

If you want to cache profile photos in the users table:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('given_name')->nullable()->after('name');
    $table->string('surname')->nullable()->after('given_name');
    $table->string('job_title')->nullable()->after('surname');
    $table->string('department')->nullable()->after('job_title');
    $table->string('profile_photo')->nullable()->after('department');
    $table->timestamp('profile_photo_updated_at')->nullable()->after('profile_photo');
});
```

---

## Styling Variations

### Different Color Schemes

Change the avatar background color based on user department or role:

```blade
@php
    $colors = [
        'bg-blue-600',
        'bg-green-600',
        'bg-purple-600',
        'bg-pink-600',
        'bg-indigo-600',
    ];
    $colorIndex = crc32(auth()->user()->email) % count($colors);
@endphp

<div class="w-10 h-10 rounded-full {{ $colors[$colorIndex] }} flex items-center justify-center text-white font-semibold text-sm">
    {{ $initials }}
</div>
```

### Larger Avatar with Status Indicator

```blade
<div class="relative">
    <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold text-sm border-2 border-white shadow">
        {{ $initials }}
    </div>
    <!-- Online status indicator -->
    <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full bg-green-400 ring-2 ring-white"></span>
</div>
```

---

## Accessibility Considerations

1. **Keyboard navigation**: Ensure dropdown can be opened/closed with keyboard
2. **Screen readers**: Use proper ARIA labels
3. **Color contrast**: Ensure initials have sufficient contrast (WCAG AA: 4.5:1)

```blade
<button @click="open = !open"
        @keydown.escape="open = false"
        aria-label="User menu"
        aria-expanded="false"
        aria-haspopup="true">
    <!-- avatar -->
</button>
```

---

## Troubleshooting

### Photos Not Appearing

1. **Check Microsoft Graph API permissions**:
   - `User.Read` - Read user profile
   - `User.ReadBasic.All` - Read basic profiles of all users

2. **Verify token has photo scope**:
   ```bash
   # Decode your access token at https://jwt.ms
   # Check "scp" claim includes User.Read
   ```

3. **Check Azure AD App Registration**:
   - API Permissions → Microsoft Graph → User.Read (Delegated)
   - Grant admin consent if required

4. **Enable photo in Entra SSO package**:
   - Some SSO packages may require explicit photo fetching configuration

### Initials Not Showing

1. **Verify AD attributes are being returned**:
   ```php
   dd(auth()->user()->toArray()); // Check for givenName and surname
   ```

2. **Update user model** to include these fields:
   ```php
   protected $fillable = [
       'name', 'email', 'given_name', 'surname', 'job_title', 'department'
   ];
   ```

### Dropdown Not Working

1. **Check Alpine.js is loaded**: View page source, search for "alpine"
2. **Check for JavaScript errors**: Open browser console (F12)
3. **Verify click-away is working**: Test clicking outside dropdown

---

## Complete Example with Tailwind CSS

```blade
<div class="flex items-center gap-4">
    <!-- User Profile -->
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open"
                @click.away="open = false"
                @keydown.escape="open = false"
                class="flex items-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 rounded-full transition-all duration-150 hover:ring-2 hover:ring-gray-300"
                aria-label="User menu"
                :aria-expanded="open">

            @if(auth()->user()->profile_photo ?? false)
                <img src="{{ auth()->user()->profile_photo }}"
                     alt="{{ auth()->user()->name }}"
                     class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">
            @else
                @php
                    $givenName = auth()->user()->given_name ?? auth()->user()->name ?? '';
                    $surname = auth()->user()->surname ?? '';
                    $initials = strtoupper(
                        substr($givenName, 0, 1) . substr($surname, 0, 1)
                    );
                @endphp
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm border-2 border-white shadow-sm">
                    {{ $initials }}
                </div>
            @endif
        </button>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="transform opacity-0 scale-95"
             x-transition:enter-end="transform opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="transform opacity-100 scale-100"
             x-transition:leave-end="transform opacity-0 scale-95"
             class="absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-xl py-1 ring-1 ring-black ring-opacity-5 z-50"
             style="display: none;">

            <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Logged in as</p>
                <p class="text-sm font-semibold text-gray-900 mt-2">
                    {{ $givenName }} {{ $surname }}
                </p>
                <p class="text-xs text-gray-600 mt-1">
                    {{ auth()->user()->email }}
                </p>
                @if(auth()->user()->job_title ?? false)
                    <p class="text-xs text-gray-500 mt-1">
                        {{ auth()->user()->job_title }}
                    </p>
                @endif
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="flex items-center w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-3 text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    Sign out
                </button>
            </form>
        </div>
    </div>

    <!-- Help Icon -->
    <a href="{{ url('/notices/help') }}"
       class="text-gray-600 hover:text-gray-900 p-2 rounded-full hover:bg-gray-100 transition-colors duration-150"
       title="Help & User Guide"
       target="_blank"
       aria-label="Help and user guide">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
        </svg>
    </a>
</div>
```

---

## Summary

**Required Steps:**

1. ✅ Add `.env` configuration for Azure AD photo fetching (optional)
2. ✅ Update header layout with user profile component
3. ✅ Ensure Alpine.js is loaded
4. ✅ Add database columns for `given_name`, `surname`, `profile_photo` (if caching)
5. ✅ Update Entra SSO callback to fetch user attributes
6. ✅ (Optional) Implement photo fetching from Microsoft Graph API

**Result:**

A Microsoft 365-style user profile component that:
- Shows initials by default
- Displays profile photo if available from AD
- Opens dropdown with user info and logout
- Positioned right of header, left of help icon
