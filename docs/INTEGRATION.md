# Integration Guide

This guide shows how to integrate the Polaris Notifications package with various authentication systems and Laravel applications.

## Table of Contents

- [Default Laravel Authentication](#default-laravel-authentication)
- [Custom Authentication Packages](#custom-authentication-packages)
- [Azure AD / Entra ID SSO](#azure-ad--entra-id-sso)
- [LDAP Authentication](#ldap-authentication)
- [SAML Authentication](#saml-authentication)
- [Role-Based Access Control](#role-based-access-control)
- [Multiple Applications](#multiple-applications)
- [API Integration](#api-integration)

## Default Laravel Authentication

The package works out-of-the-box with Laravel's default authentication:

```php
// config/notices.php
'dashboard' => [
    'enabled' => true,
    'route_prefix' => 'notices',
    'middleware' => ['web', 'auth'],  // Uses default 'web' guard
],
```

No additional configuration needed. Users must log in before accessing the dashboard.

## Custom Authentication Packages

### General Approach

The package is authentication-agnostic. It simply uses Laravel's middleware system.

**Step 1:** Install your auth package
**Step 2:** Configure the notifications middleware to use your auth system

```php
// config/notices.php
'dashboard' => [
    'middleware' => ['web', 'auth:your-guard-name'],
],
```

Or use custom middleware from your auth package:

```php
'dashboard' => [
    'middleware' => ['web', 'your-auth-middleware'],
],
```

## Azure AD / Entra ID SSO

### Example with Custom Entra SSO Package

If using an Entra ID/Azure AD SSO package:

```php
// config/notices.php
'dashboard' => [
    'enabled' => true,
    'route_prefix' => 'notices',

    // Option 1: Use default auth (if SSO modifies default guard)
    'middleware' => ['web', 'auth'],

    // Option 2: Specify custom guard
    // 'middleware' => ['web', 'auth:entra'],

    // Option 3: Use SSO middleware directly
    // 'middleware' => ['web', 'entra.auth'],
],

'api' => [
    // Use Azure AD bearer tokens for API
    'enabled' => true,
    'middleware' => ['api', 'auth:azure-api'],

    // Or disable if not using API
    // 'enabled' => false,
],
```

![Configuration Example](images/config-notifications.png)
*Example configuration file with SSO settings*

### Example with Laravel Socialite

If using Socialite for Azure AD:

```php
'dashboard' => [
    'middleware' => ['web', 'auth'],  // Socialite uses default guard
],
```

## LDAP Authentication

### Example with LdapRecord-Laravel

```php
// config/notices.php
'dashboard' => [
    'middleware' => ['web', 'auth:ldap'],  // Use LDAP guard
],
```

Or combine with role checks:

```php
'dashboard' => [
    'middleware' => ['web', 'auth:ldap', 'ldap.groups:staff,admin'],
],
```

## SAML Authentication

### Example with Laravel-SAML2

```php
// config/notices.php
'dashboard' => [
    'middleware' => ['web', 'saml'],  // Use SAML middleware
],
```

Or with guard:

```php
'dashboard' => [
    'middleware' => ['web', 'auth:saml2'],
],
```

## Role-Based Access Control

### Using Laravel Gates

Define a gate in your `AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('view-notifications', function ($user) {
        return $user->isStaff() || $user->isAdmin();
    });
}
```

Use the gate:

```php
// config/notices.php
'dashboard' => [
    'middleware' => ['web', 'auth', 'can:view-notifications'],
],
```

### Using Roles (Spatie Permission, Bouncer, etc.)

With Spatie Laravel-Permission:

```php
'dashboard' => [
    'middleware' => ['web', 'auth', 'role:staff|admin'],
],
```

Or create custom middleware:

```php
'dashboard' => [
    'middleware' => ['web', 'auth', 'permission:view-notifications-dashboard'],
],
```

### Custom Authorization Middleware

Create your own middleware:

```php
// app/Http/Middleware/AuthorizeNotificationsDashboard.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthorizeNotificationsDashboard
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Your custom authorization logic
        if (!$user || !$user->canAccessNotifications()) {
            abort(403, 'Unauthorized access to notifications dashboard');
        }

        return $next($request);
    }
}
```

Register it:

```php
// bootstrap/app.php (Laravel 11) or app/Http/Kernel.php (Laravel 10)
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'notifications.authorize' => \App\Http\Middleware\AuthorizeNotificationsDashboard::class,
    ]);
})
```

Use it:

```php
// config/notices.php
'dashboard' => [
    'middleware' => ['web', 'auth', 'notifications.authorize'],
],
```

## Multiple Applications

### Shared Package, Different Auth Per App

**App 1 (Staff Portal - Uses SSO):**
```php
// config/notices.php
'dashboard' => [
    'enabled' => true,
    'route_prefix' => 'admin/notifications',
    'middleware' => ['web', 'auth:entra', 'role:staff'],
],
```

**App 2 (Public Portal - Uses Sanctum):**
```php
// config/notices.php
'dashboard' => [
    'enabled' => false,  // No dashboard in public app
],
'api' => [
    'enabled' => true,
    'middleware' => ['api', 'auth:sanctum'],
],
```

### Multiple Dashboards

Create multiple route configurations:

```php
// routes/web.php (in your application)
use Dcplibrary\Notices\Http\Controllers\DashboardController;

// Admin dashboard
Route::middleware(['web', 'auth', 'role:admin'])
    ->prefix('admin/notifications')
    ->name('admin.notifications.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        // ... other routes
    });

// Staff dashboard (read-only)
Route::middleware(['web', 'auth', 'role:staff'])
    ->prefix('staff/notifications')
    ->name('staff.notifications.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    });

// Disable default package routes
// config/notices.php: 'dashboard' => ['enabled' => false]
```

## API Integration

### Public API (No Auth)

For public read-only access:

```php
// config/notices.php
'api' => [
    'enabled' => true,
    'middleware' => ['api'],  // No auth required
    'rate_limit' => 60,
],
```

### API with API Keys

Create custom middleware for API key authentication:

```php
// app/Http/Middleware/ApiKeyAuth.php
public function handle(Request $request, Closure $next)
{
    $apiKey = $request->header('X-API-Key');

    if (!$apiKey || !$this->isValidApiKey($apiKey)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    return $next($request);
}
```

Use it:

```php
'api' => [
    'middleware' => ['api', 'api.key'],
],
```

### API with OAuth2 (Laravel Passport)

```php
'api' => [
    'middleware' => ['api', 'auth:api'],  // Passport uses 'api' guard
],
```

Or require specific scopes:

```php
'api' => [
    'middleware' => ['api', 'auth:api', 'scope:read-notifications'],
],
```

### API with Personal Access Tokens (Sanctum)

Default configuration works:

```php
'api' => [
    'middleware' => ['api', 'auth:sanctum'],
],
```

Users generate tokens:

```php
$token = $user->createToken('notifications-api')->plainTextToken;
```

## Environment-Specific Configuration

Use environment variables for flexibility:

```php
// config/notices.php
'dashboard' => [
    'enabled' => env('NOTIFICATIONS_DASHBOARD_ENABLED', true),
    'middleware' => explode(',', env('NOTIFICATIONS_DASHBOARD_MIDDLEWARE', 'web,auth')),
],

'api' => [
    'enabled' => env('NOTIFICATIONS_API_ENABLED', true),
    'middleware' => explode(',', env('NOTIFICATIONS_API_MIDDLEWARE', 'api,auth:sanctum')),
],
```

Then in `.env`:

```env
# Production - SSO with strict access
NOTIFICATIONS_DASHBOARD_MIDDLEWARE=web,auth:entra,role:staff

# Development - Simple auth
NOTIFICATIONS_DASHBOARD_MIDDLEWARE=web,auth

# Staging - API only
NOTIFICATIONS_DASHBOARD_ENABLED=false
NOTIFICATIONS_API_ENABLED=true
```

## Common Patterns

### Pattern 1: Internal Staff Dashboard

```php
'dashboard' => [
    'enabled' => true,
    'middleware' => ['web', 'auth', 'verified', 'role:staff|admin'],
],
'api' => [
    'enabled' => false,
],
```

### Pattern 2: API-Only (Custom Frontend)

```php
'dashboard' => [
    'enabled' => false,
],
'api' => [
    'enabled' => true,
    'middleware' => ['api', 'auth:sanctum'],
],
```

### Pattern 3: Public Dashboard, Private API

```php
'dashboard' => [
    'enabled' => true,
    'middleware' => ['web'],  // No auth
],
'api' => [
    'enabled' => true,
    'middleware' => ['api', 'auth:sanctum', 'role:admin'],
],
```

### Pattern 4: SSO Dashboard, OAuth API

```php
'dashboard' => [
    'middleware' => ['web', 'auth:entra'],
],
'api' => [
    'middleware' => ['api', 'auth:api', 'scope:notifications'],
],
```

## Troubleshooting

### Dashboard returns 401/403

**Cause:** Authentication failing
**Solution:** Check which guard/middleware your auth package uses

```bash
# In tinker
php artisan tinker
>>> config('auth.guards')
>>> config('auth.defaults')
```

### API returns 401

**Cause:** Wrong guard or missing token
**Solution:**
1. Check API guard configuration
2. Verify token is being sent correctly
3. Test with a simple route first

### Infinite redirect loop

**Cause:** Middleware protecting login route
**Solution:** Ensure your auth package's login routes aren't protected by the same middleware

### Routes not found

**Cause:** Routes disabled or config cached
**Solution:**
```bash
php artisan config:clear
php artisan route:list | grep notifications
```

![Route List](images/route-list.png)
*Example output showing registered notification routes*

## Testing Integration

### Test Authentication

```php
// tests/Feature/NotificationsDashboardTest.php

public function test_dashboard_requires_authentication()
{
    $response = $this->get('/notifications');

    $response->assertRedirect('/login');  // Or your SSO login route
}

public function test_authenticated_user_can_access_dashboard()
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/notifications');

    $response->assertOk();
}
```

### Test Authorization

```php
public function test_non_staff_cannot_access_dashboard()
{
    $user = User::factory()->create(['role' => 'public']);

    $response = $this->actingAs($user)->get('/notifications');

    $response->assertForbidden();
}
```

## Best Practices

1. **Keep package flexible** - Don't hardcode auth in the package
2. **Configure per environment** - Use `.env` for differences
3. **Test thoroughly** - Verify auth works before deploying
4. **Document for your team** - Note which auth system you're using
5. **Use gates for fine-grained control** - Better than hardcoded roles
6. **Disable unused features** - Turn off API if not needed

## Examples Repository

For complete working examples, see the package examples directory (if available) or create your own integration examples based on these patterns.

## Need Help?

If your specific authentication system isn't covered here, the general pattern is:

1. Identify your auth middleware/guard
2. Add it to the notifications config
3. Test that it works
4. Add any additional authorization logic

The package doesn't care what authentication system you use - it just uses Laravel's middleware system.
