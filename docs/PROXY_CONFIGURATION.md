# Proxy Configuration for nginx-proxy

This document explains how to configure the main Laravel application to work correctly behind nginx-proxy.

## Problem

When the application is behind an nginx-proxy that handles SSL termination, Laravel generates URLs with incorrect protocol/port combinations (e.g., `https://domain:80`), causing `ERR_SSL_PROTOCOL_ERROR` or incorrect OAuth redirects.

## Solution

The main Laravel application (not this package) **MUST** be configured to trust proxy headers. The notices package does NOT attempt to configure proxy detection - it must be done in your main app's `bootstrap/app.php`.

### Required Configuration

In your main application's `bootstrap/app.php`, add the following trust proxies configuration:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies - this tells Laravel to trust X-Forwarded-* headers
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

### Important Notes

1. **Use the correct Request class**: Import `Illuminate\Http\Request` (NOT `Illuminate\Support\Facades\Request`)
2. **Headers parameter**: Use `Request::HEADER_X_FORWARDED_FOR` etc. - these are constants on the Request class
3. **Trust all proxies**: Using `at: '*'` trusts all proxies. In production, you might want to specify specific proxy IPs

### What This Fixes

- ✅ SSO/OAuth redirects after login (e.g., `/entra/dashboard`, `/entra/callback`)
- ✅ Navigation links throughout the application
- ✅ Any Laravel-generated URLs via `route()` or `url()` helpers
- ✅ Asset URLs and other framework-generated URLs
- ✅ Prevents `https://domain:80` URLs that cause SSL protocol errors

### Package-Level Fixes Already Applied

The notices package has been updated to work correctly behind proxies:
- ✅ Uses relative URLs in all navigation and forms to avoid protocol/port issues
- ✅ Uses relative URLs in JavaScript fetch calls
- ✅ Disables output buffering for streaming responses
- ✅ Adds proxy-friendly headers (`X-Accel-Buffering: no`) for streaming endpoints
- ✅ **Does NOT** attempt to globally configure URL generation (which would interfere with the main app)

### Testing

After applying this configuration:

1. Restart your application/PHP-FPM
2. Test SSO login - should redirect to correct URL without port 80
3. Test all navigation links - should work without SSL errors
4. Test the Livewire sync component - streaming should work correctly

### Nginx Configuration (Optional)

If you continue to have streaming issues, add this to your nginx proxy configuration:

```nginx
location ~ ^/notices/sync/ftp-files/stream {
    proxy_pass http://your-app;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 3600s;

    # Disable compression
    gzip off;
}
```

## Troubleshooting OAuth/SSO Redirect Issues

### Problem: Login redirects to `https://domain:80`

If the dashboard loads correctly but clicking "Login" redirects to `https://notices.dcplibrary.org:80/entra/dashboard`, follow these steps:

#### 1. Clear Laravel Configuration Cache

The cached configuration may still have the old APP_URL value:

```bash
# Navigate to your main Laravel application directory
cd /path/to/main/laravel/app

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Restart PHP-FPM/web server
sudo systemctl restart php8.3-fpm  # Adjust PHP version as needed
```

#### 2. Verify APP_URL Configuration

Check your `.env` file:

```bash
# Should NOT have :80 in the URL
APP_URL=https://notices.dcplibrary.org
ASSET_URL=https://notices.dcplibrary.org
```

After confirming, regenerate config cache:

```bash
php artisan config:cache
```

#### 3. Check Microsoft Entra App Registration

The redirect URI in Microsoft Entra may have `:80` hardcoded:

1. Log in to [Azure Portal](https://portal.azure.com)
2. Navigate to **Microsoft Entra ID** → **App registrations**
3. Find your application
4. Go to **Authentication** → **Redirect URIs**
5. Ensure all redirect URIs are:
   - `https://notices.dcplibrary.org/entra/callback` (NO :80)
   - Remove any URIs with `:80` in them
6. Save changes

#### 4. Check Entra SSO Package Configuration

If using `dcplibrary/entra-sso`, check its config file:

```bash
# Check if config is published
ls -la config/entra.php

# If exists, check for hardcoded URLs
grep -r "80" config/entra.php

# Clear and recache if changes made
php artisan config:clear
php artisan config:cache
```

#### 5. Test in Incognito/Private Mode

Browser cache can hold the old OAuth redirect:

1. Open incognito/private browsing window
2. Navigate to `https://notices.dcplibrary.org`
3. Try logging in
4. If it works, clear browser cache in normal mode

#### 6. Check nginx-proxy Configuration

Verify the nginx-proxy is correctly forwarding headers:

```bash
# Check nginx-proxy logs
docker logs nginx-proxy

# Look for X-Forwarded-Proto headers in requests
```

The nginx-proxy should be setting:
- `X-Forwarded-Proto: https`
- `X-Forwarded-Port: 443`
- `X-Forwarded-Host: notices.dcplibrary.org`

#### 7. Verify Trust Proxies Configuration

Double-check `bootstrap/app.php` has the correct configuration (as shown above) and that PHP-FPM was restarted after adding it.

#### 8. Check for Hardcoded URLs

Search for any hardcoded URLs with `:80`:

```bash
# In main Laravel app directory
grep -r "notices.dcplibrary.org:80" .
grep -r ":80" config/
```

### Common Causes

1. **Cached configuration**: Laravel cached old APP_URL before it was changed
2. **Entra redirect URI mismatch**: Azure portal has wrong redirect URI
3. **Browser cache**: Old OAuth redirect cached by browser
4. **PHP-FPM not restarted**: Changes to bootstrap/app.php require restart
5. **nginx-proxy headers**: Proxy not sending correct X-Forwarded-Proto header
