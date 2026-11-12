# API

The HTTP API lives under `/api/v1/notices`.

- **Endpoint reference:** [openapi.yaml](openapi.yaml), browsable in a running install at `/notices/api-docs`. It is the single description of the endpoints, and a test fails if it falls out of step with the registered routes.
- **This page:** how to configure access to the API in your Laravel application.

## Table of Contents

1. [Authentication Setup](#authentication-setup)
2. [Middleware Configuration](#middleware-configuration)
3. [CORS Configuration](#cors-configuration)
4. [Rate Limiting](#rate-limiting)
5. [API Versioning](#api-versioning)
6. [Environment Variables](#environment-variables)

---

## Authentication Setup

The API requires authentication. Choose one of the following methods:

### Option 1: Laravel Sanctum (Recommended)

**Install Sanctum:**
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

**Configure Sanctum:**

In `config/sanctum.php`:
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),
```

**Package route middleware:**

The package API manifest (`routes/api.php`) already applies:

```php
->middleware(['api', 'auth:sanctum'])
```

You normally do **not** need to re-wrap package routes with additional auth middleware unless your host application has custom requirements.

**Generate Token:**
```php
// In your authentication controller
$token = $user->createToken('api-token')->plainTextToken;
```

### Token Abilities (Permissions)

This package uses Sanctum token abilities for write/admin endpoints:

- `issues:write`: required for issue resolution endpoints
- `admin:maintenance`: required for admin maintenance endpoints (queue imports/cleanup, job run status)

Notes:
- A token with ability `*` can access everything.
- The package includes a Settings UI to create/revoke tokens at `/notices/settings/api-tokens` (host app must have Sanctum installed and the User model must `use HasApiTokens`).

### Option 2: Laravel Passport

**Install Passport:**
```bash
composer require laravel/passport
php artisan migrate
php artisan passport:install
```

**Update User Model:**
```php
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}
```

**Update Auth Service Provider:**
```php
use Laravel\Passport\Passport;

public function boot()
{
    Passport::tokensExpireIn(now()->addDays(15));
    Passport::refreshTokensExpireIn(now()->addDays(30));
}
```

**Update API Routes:**
```php
Route::middleware('auth:api')->group(function () {
    // Notices API routes
});
```

---

## Middleware Configuration

The package API routes already run with `api` + `auth:sanctum`.

Host apps typically only add:
- global API middleware (logging, tracing, throttling)
- CORS policy
- custom ability/authorization policy rules

Avoid redefining package routes unless you need to intentionally fork behavior.

---

## CORS Configuration

If accessing the API from a different domain, configure CORS:

**Install CORS package (if not already installed):**
```bash
composer require fruitcake/laravel-cors
```

**Publish CORS config:**
```bash
php artisan vendor:publish --tag="cors"
```

**Configure CORS:**

In `config/cors.php`:
```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://your-frontend-domain.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
```

---

## Rate Limiting

Add rate limiting to protect your API:

**In `app/Providers/RouteServiceProvider.php`:**

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

protected function configureRateLimiting()
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Stricter limit for API endpoints
    RateLimiter::for('notices-api', function (Request $request) {
        return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
    });
}
```

**Apply to API routes:**

In `routes/api.php` or your route configuration:
```php
Route::middleware(['api', 'auth:sanctum', 'throttle:notices-api'])->group(function () {
    // API routes
});
```

---

## API Versioning

The API is currently at version 1 (`/api/v1/notices`). To add versioning:

**Create versioned route files:**

```
routes/
  api/
    v1.php  (current routes)
    v2.php  (future version)
```

**Update service provider:**

In `src/NoticesServiceProvider.php`:
```php
public function boot()
{
    // Load versioned routes
    $this->loadRoutesFrom(__DIR__ . '/../routes/api/v1.php');
}
```

**Version negotiation:**

Create middleware to handle version negotiation:
```php
<?php

namespace Dcplibrary\Notices\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiVersion
{
    public function handle(Request $request, Closure $next, string $version)
    {
        $request->headers->set('API-Version', $version);
        
        return $next($request);
    }
}
```

---

## Environment Variables

### Queue Configuration (for queued admin actions)

Admin endpoints (e.g. `/api/v1/notices/admin/import`) queue work instead of running long processes inside the HTTP request.

If you are **not using Redis**, use Laravel's database queue:

```env
QUEUE_CONNECTION=database
```

Then create the queue tables and run a worker:

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```

Production note: run `queue:work` under Supervisor/systemd so it stays running.

Add these to your `.env` file:

```env
# API Configuration
API_PREFIX=api/v1/notices
API_RATE_LIMIT=120

# Sanctum Configuration (if using Sanctum)
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1

# CORS Configuration
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://your-frontend-domain.com
```

---

## Testing Authentication

### Generate Test Token

**Using Tinker:**
```bash
php artisan tinker
```

```php
$user = User::first();
$token = $user->createToken('test-token')->plainTextToken;
echo $token;
```

### Test API Endpoint

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost/api/v1/notices/dashboard?date_from=2026-01-13"
```

### Test in Swagger UI

1. Navigate to `/notices/api-docs`
2. Enter your token in the token field
3. Click "Save Token"
4. Test endpoints using "Try it out"

---

## Security Best Practices

1. **Use HTTPS in Production**
   - Always use HTTPS for API endpoints in production
   - Configure SSL certificates properly

2. **Token Expiration**
   - Set reasonable token expiration times
   - Implement token refresh mechanism

3. **Rate Limiting**
   - Implement rate limiting to prevent abuse
   - Use different limits for authenticated vs unauthenticated users

4. **Input Validation**
   - All endpoints use request validation classes
   - Validate all user input

5. **Error Handling**
   - Don't expose sensitive information in error messages
   - Use consistent error response format

6. **Logging**
   - Log API requests for auditing
   - Monitor for suspicious activity

---

## Troubleshooting

### 401 Unauthorized Errors

- Verify token is being sent: `Authorization: Bearer YOUR_TOKEN`
- Check token hasn't expired
- Verify middleware is configured correctly
- Check user has necessary permissions

### CORS Errors

- Verify CORS configuration in `config/cors.php`
- Check allowed origins include your frontend domain
- Ensure `supports_credentials` is true if using cookies

### Rate Limit Errors

- Check rate limit configuration
- Verify limits aren't too restrictive
- Consider increasing limits for authenticated users

### Route Not Found

- Verify routes are loaded: `php artisan route:list | grep notices`
- Check route prefix matches your configuration
- Ensure service provider is registered

---

## Next Steps

1. Configure authentication (Sanctum or Passport)
2. Set up CORS for your frontend domain
3. Configure rate limiting
4. Test endpoints using Swagger UI
5. Generate API tokens for your application
6. Integrate API client in your frontend

For more information, see:
- [API Endpoints Documentation](openapi.yaml)
