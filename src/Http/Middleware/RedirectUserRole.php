<?php

namespace Dcplibrary\Notices\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to redirect users with role='user' to the user portal.
 * Admin users can access all views.
 */
class RedirectUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(Request): (Response) $next
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $user = $request->user();

        // Debug logging
        \Log::info('RedirectUserRole middleware', [
            'user'       => $user?->email,
            'role'       => $user?->role,
            'path'       => $request->path(),
            'route_name' => $request->route()?->getName(),
        ]);

        // If not authenticated, let the auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // If user is not an admin, restrict to user portal only
        // Admin roles: 'admin', null/empty (default to admin for backwards compatibility)
        // Non-admin roles: 'user', 'yes', or any other value
        $isAdmin = in_array($user->role, ['admin', null, ''], true);

        if (!$isAdmin) {
            // Allow access to the user portal routes
            if ($request->routeIs('notices.user-portal.*')) {
                return $next($request);
            }

            // Allow logout and other auth routes
            if ($request->routeIs('entra.*') || $request->routeIs('logout')) {
                return $next($request);
            }

            // Check URL path as fallback (for routes without proper names like 'notices.')
            $path = $request->path();
            if (str_starts_with($path, 'notices/notices-viewer')) {
                return $next($request);
            }

            // Redirect all other notices routes to the user portal
            return redirect()->route('notices.user-portal.index');
        }

        // Admin users can access everything
        return $next($request);
    }
}
