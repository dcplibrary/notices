<?php

// API Documentation (Swagger UI)
Route::get('/api-docs', function () {
    return view('notices::api-docs');
})->name('api-docs');

// Serve OpenAPI specification file
Route::get('/api-docs/openapi.yaml', function () {
    // Get package base path - this file is in routes/web/, so parent^2 is package root.
    $packagePath = dirname(__DIR__, 2);
    $filePath = $packagePath . '/docs/openapi.yaml';

    // Try multiple possible paths
    $possiblePaths = [
        $filePath,
        base_path('vendor/dcplibrary/notices/docs/openapi.yaml'),
        dirname(__DIR__) . '/docs/openapi.yaml',
    ];

    $foundPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $foundPath = $path;
            break;
        }
    }

    if (!$foundPath) {
        abort(404, 'OpenAPI specification not found. Checked paths: ' . implode(', ', $possiblePaths));
    }

    $content = file_get_contents($foundPath);

    // Remove any BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    // Remove ALL leading whitespace (spaces, tabs, newlines) to ensure file starts at column 0
    $content = preg_replace('/^[\s\n\r]+/', '', $content);

    // Ensure it starts with openapi: (no leading spaces)
    if (!preg_match('/^openapi:\s*3\.\d+\.\d+/', $content)) {
        // If still doesn't match, try to find and extract from first line
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && preg_match('/^openapi:\s*3\.\d+\.\d+/', $trimmed)) {
                // Found the openapi line, rebuild content from here
                $content = implode("\n", array_slice($lines, array_search($line, $lines)));
                break;
            }
        }
    }

    // Final validation
    if (!preg_match('/^openapi:\s*3\.\d+\.\d+/', $content)) {
        abort(500, 'Invalid OpenAPI specification format. File: ' . $foundPath . '. First 100 chars: ' . substr($content, 0, 100));
    }

    // Replace server URLs with actual app URL from config
    // This ensures Swagger UI uses the correct base URL (including port if needed)
    $appUrl = rtrim(config('app.url'), '/');
    $apiBaseUrl = $appUrl . '/api/v1/notices';

    // Replace hardcoded server URLs in the YAML content
    $content = preg_replace(
        '/url:\s*(http:\/\/localhost|https?:\/\/[^\s]+)\/api\/v1\/notices/',
        'url: ' . $apiBaseUrl,
        $content
    );

    // Also replace any placeholder URLs
    $content = preg_replace(
        '/url:\s*https:\/\/your-domain\.com\/api\/v1\/notices/',
        'url: ' . $apiBaseUrl,
        $content
    );

    // Serve with correct content type for Swagger UI
    return response($content, 200)
        ->header('Content-Type', 'text/yaml; charset=utf-8')
        ->header('Content-Disposition', 'inline; filename="openapi.yaml"')
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Cache-Control', 'public, max-age=3600');
})->name('api-docs.openapi');
