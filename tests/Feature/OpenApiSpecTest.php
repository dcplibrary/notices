<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps docs/openapi.yaml honest.
 *
 * The spec is written by hand and served at /notices/api-docs. These tests
 * fail when an API route is added, removed or renamed without the spec
 * being updated to match.
 */
class OpenApiSpecTest extends TestCase
{
    private const PREFIX = 'api/v1/notices';

    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    #[Test]
    public function spec_is_valid_yaml(): void
    {
        $spec = Yaml::parseFile($this->specPath());

        $this->assertIsArray($spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertNotEmpty($spec['paths']);
    }

    #[Test]
    public function every_api_route_is_documented_and_every_documented_operation_exists(): void
    {
        $documented = $this->documentedOperations();
        $registered = $this->registeredOperations();

        $this->assertSame(
            [],
            array_values(array_diff($registered, $documented)),
            'These API routes are missing from docs/openapi.yaml.'
        );

        $this->assertSame(
            [],
            array_values(array_diff($documented, $registered)),
            'docs/openapi.yaml documents these operations, but no such route is registered.'
        );
    }

    /** @return list<string> e.g. "GET /notifications/{id}" */
    private function documentedOperations(): array
    {
        $operations = [];

        foreach (Yaml::parseFile($this->specPath())['paths'] as $path => $item) {
            foreach (array_keys($item) as $method) {
                if (in_array($method, self::METHODS, true)) {
                    $operations[] = strtoupper($method) . ' ' . $path;
                }
            }
        }

        sort($operations);

        return $operations;
    }

    /** @return list<string> */
    private function registeredOperations(): array
    {
        $operations = [];

        foreach (Route::getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');

            if (!str_starts_with($uri, self::PREFIX)) {
                continue;
            }

            $path = substr($uri, strlen(self::PREFIX)) ?: '/';

            foreach ($route->methods() as $method) {
                if ('HEAD' !== $method) {
                    $operations[] = $method . ' ' . $path;
                }
            }
        }

        sort($operations);

        return $operations;
    }

    private function specPath(): string
    {
        return dirname(__DIR__, 2) . '/docs/openapi.yaml';
    }
}
