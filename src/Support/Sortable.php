<?php

namespace Dcplibrary\Notices\Support;

use Illuminate\Http\Request;

/**
 * Sortable.
 *
 * Shared helper for resolving safe sort column + direction from the request.
 *
 * Usage:
 *
 *   [$sortColumn, $sortDir, $sortBy] = $this->resolveSort(
 *       request(),
 *       [
 *           'first_exported_at' => 'first_exported_at',
 *           'patron_barcode'    => 'patron_barcode',
 *           'status'            => 'status',
 *       ],
 *       'first_exported_at',
 *       'desc',
 *   );
 *
 * - $sortBy is the *requested* key (for views / query strings)
 * - $sortColumn is the actual DB column used in orderBy()
 * - $sortDir is normalised to 'asc' or 'desc'.
 */
trait Sortable
{
    /**
     * Resolve a safe sort column + direction from the request.
     *
     * @param Request $request    Incoming HTTP request
     * @param array   $allowed    Map of allowed sort keys → DB columns
     * @param string  $defaultKey Default sort key (e.g. 'first_exported_at')
     * @param string  $defaultDir Default direction ('asc' or 'desc')
     *
     * @return array{0:string,1:string,2:string} [$column, $dir, $sortBy]
     */
    protected function resolveSort(Request $request, array $allowed, string $defaultKey, string $defaultDir = 'desc'): array
    {
        $requestedKey = (string) $request->input('sort', $defaultKey);
        $requestedDir = strtolower((string) $request->input('dir', $defaultDir));

        $column = $allowed[$requestedKey] ?? $allowed[$defaultKey] ?? reset($allowed) ?: $defaultKey;
        $dir    = 'asc' === $requestedDir ? 'asc' : 'desc';

        return [$column, $dir, $requestedKey];
    }
}
