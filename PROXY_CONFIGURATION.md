# Proxy Configuration for nginx-proxy

This document explains how to configure the main Laravel application to work correctly behind nginx-proxy.

## Problem

When the application is behind an nginx-proxy that handles SSL termination, Laravel generates URLs with incorrect protocol/port combinations (e.g., `https://domain:80`), causing `ERR_SSL_PROTOCOL_ERROR`.

## Solution

The main Laravel application (not this package) needs to be configured to trust proxy headers.

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

- ✅ SSO redirects after login (e.g., `/entra/dashboard`)
- ✅ Navigation links throughout the application
- ✅ Any Laravel-generated URLs via `route()` or `url()` helpers
- ✅ Asset URLs and other framework-generated URLs

### Fixes Already Applied in This Package

The notices package has already been updated to:
- Use relative URLs in navigation and fetch calls
- Disable output buffering for streaming responses
- Add proxy-friendly headers for streaming endpoints

These package-level fixes ensure the notices dashboard works behind a proxy even if some URLs are still generated incorrectly by the framework.

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
