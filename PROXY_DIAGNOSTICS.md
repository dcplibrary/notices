# Proxy Configuration Diagnostics

## Quick Test - Run This Command

In your **main Laravel application** directory (NOT the notices package), run:

```bash
php artisan tinker
```

Then paste this:

```php
$request = request();
echo "Scheme: " . $request->getScheme() . "\n";
echo "Host: " . $request->getHost() . "\n";
echo "Port: " . $request->getPort() . "\n";
echo "HTTP Host: " . $request->getHttpHost() . "\n";
echo "Scheme and HTTP Host: " . $request->getSchemeAndHttpHost() . "\n";
echo "Is Secure: " . ($request->isSecure() ? 'YES' : 'NO') . "\n";
echo "URL: " . url('/test') . "\n";
echo "\nHeaders from proxy:\n";
echo "X-Forwarded-Proto: " . $request->header('X-Forwarded-Proto') . "\n";
echo "X-Forwarded-Port: " . $request->header('X-Forwarded-Port') . "\n";
echo "X-Forwarded-Host: " . $request->header('X-Forwarded-Host') . "\n";
```

**Expected Output:**
```
Scheme: https
Host: notices.dcplibrary.org
Port: 443
HTTP Host: notices.dcplibrary.org
Scheme and HTTP Host: https://notices.dcplibrary.org
Is Secure: YES
URL: https://notices.dcplibrary.org/test

Headers from proxy:
X-Forwarded-Proto: https
X-Forwarded-Port: 443
X-Forwarded-Host: notices.dcplibrary.org
```

**If you're getting port 80 or `https://notices.dcplibrary.org:80`**, the trustProxies configuration is NOT working.

---

## Verification Steps

### 1. Verify bootstrap/app.php Configuration

Open your **main Laravel app's** `bootstrap/app.php` and verify it looks EXACTLY like this:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;  // ← MUST be this import, NOT the Facade

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                    Request::HEADER_X_FORWARDED_HOST |
                    Request::HEADER_X_FORWARDED_PORT |
                    Request::HEADER_X_FORWARDED_PROTO |
                    Request::HEADER_X_FORWARDED_PREFIX
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
```

### 2. Check the Import Statement

**CRITICAL:** Make sure the import at the top is:
```php
use Illuminate\Http\Request;
```

**NOT:**
```php
use Illuminate\Support\Facades\Request;  // WRONG!
```

### 3. Clear ALL Caches

In your **main Laravel app** directory:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

### 4. Restart PHP-FPM

Changes to `bootstrap/app.php` require restarting PHP-FPM:

```bash
# Check which PHP version you're using
php -v

# Restart PHP-FPM (adjust version as needed)
sudo systemctl restart php8.3-fpm
# OR
sudo systemctl restart php8.2-fpm
# OR
sudo systemctl restart php-fpm
```

### 5. Verify Restart Worked

```bash
sudo systemctl status php8.3-fpm  # Adjust version
```

Should show "active (running)".

### 6. Test Again

After restarting PHP-FPM, visit `https://notices.dcplibrary.org` and try logging in.

---

## Common Mistakes

### ❌ Wrong Import
```php
use Illuminate\Support\Facades\Request;  // This is the FACADE - WRONG!
```

### ✅ Correct Import
```php
use Illuminate\Http\Request;  // This is the Request CLASS - CORRECT!
```

### ❌ Forgot to Restart PHP-FPM
Changes to `bootstrap/app.php` are only loaded when PHP-FPM starts, not on every request.

### ❌ Wrong Syntax
Make sure you're using Laravel 11+ syntax with `->withMiddleware()`, not the old Laravel 10 style.

### ❌ Config Cached
If you had previously cached config, it might still be using old settings. Always clear caches after changes.

---

## If It's STILL Not Working

### Check nginx-proxy Headers

The nginx-proxy container should be setting these headers. Check its logs:

```bash
docker logs nginx-proxy | tail -50
```

Or check if the headers are being sent:

```bash
# In your main Laravel app, create a test route temporarily:
Route::get('/debug-headers', function (Illuminate\Http\Request $request) {
    dd([
        'all_headers' => $request->headers->all(),
        'server_vars' => $request->server->all(),
    ]);
});
```

Visit `https://notices.dcplibrary.org/debug-headers` and check if you see:
- `x-forwarded-proto: ["https"]`
- `x-forwarded-port: ["443"]`
- `x-forwarded-host: ["notices.dcplibrary.org"]`

If these headers are MISSING, the problem is with your nginx-proxy configuration, not Laravel.

### Check for Conflicting Middleware

If you have an old `TrustProxies` middleware class in `app/Http/Middleware/TrustProxies.php`, it might be conflicting. Check if it exists:

```bash
ls -la app/Http/Middleware/TrustProxies.php
```

If it exists, either delete it or ensure it's not registered in your middleware stack.

---

## What Should Happen

Once trustProxies is configured correctly:

1. ✅ `$request->getScheme()` returns `"https"` (not `"http"`)
2. ✅ `$request->getPort()` returns `443` (not `80`)
3. ✅ `$request->isSecure()` returns `true`
4. ✅ `url('/test')` generates `https://notices.dcplibrary.org/test` (no `:80`)
5. ✅ OAuth redirects work correctly without port in URL
6. ✅ All `route()` and `url()` helpers generate correct URLs

---

## Still Having Issues?

If you've verified ALL of the above and it's still not working, show me:

1. The exact contents of your `bootstrap/app.php` file
2. The output of the Tinker diagnostic commands
3. The output of `php -v` and `sudo systemctl status php8.3-fpm`
4. Whether the headers are present in the `/debug-headers` test route
