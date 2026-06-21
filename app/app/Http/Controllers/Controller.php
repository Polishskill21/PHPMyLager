<?php

namespace App\Http\Controllers;

use App\Support\DomainCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

abstract class Controller
{
    /** Default rows fetched per load-more chunk. */
    protected const LIST_PER_PAGE = 50;

    // ──────────────────────────────────────────────────────────────────────────
    // Load-more list pagination (shared by every list controller)
    //
    // A list is browsed one "chunk" at a time. The first chunk is rendered
    // server-side (SSR) from firstChunkData(); subsequent chunks are fetched as
    // JSON by the frontend from renderListChunk() and appended.
    //
    // The DEFAULT view (no search/filter/sort) is cached per page via DomainCache
    // and shared between the SSR first paint and the API page-1 fetch. Filtered or
    // sorted views run live — they are already cheap under the LIMIT and have a
    // low cache hit-rate. Writes bump the domain version (DomainCache::flush) and
    // drop every cached chunk for that domain at once.
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build one chunk of a list (formatted rows + pagination meta).
     *
     * @param  string                                   $domain     DomainCache domain
     * @param  Builder                                  $query      filtered, ordered browse query
     * @param  bool                                     $cacheable  true => default view (cache it)
     * @param  callable(\Illuminate\Database\Eloquent\Model): array $formatRow
     * @return array{rows: array<int, array>, meta: array{page:int, perPage:int, total:int, hasMore:bool}}
     */
    protected function listChunkData(
        string $domain,
        Builder $query,
        bool $cacheable,
        callable $formatRow,
        Request $request,
        int $perPage = self::LIST_PER_PAGE
    ): array {
        $page = max(1, (int) $request->query('page', 1));

        $slice = $cacheable
            ? DomainCache::remember($domain, "list:page:{$page}:per:{$perPage}", fn () => $this->sliceRows($query, $page, $perPage, $formatRow))
            : $this->sliceRows($query, $page, $perPage, $formatRow);

        $total = $cacheable
            ? (int) DomainCache::remember($domain, 'list:count', fn () => (clone $query)->reorder()->count())
            : (clone $query)->reorder()->count();

        return [
            'rows' => $slice['rows'],
            'meta' => [
                'page'    => $page,
                'perPage' => $perPage,
                'total'   => $total,
                'hasMore' => $slice['hasMore'],
            ],
        ];
    }

    /**
     * Same as listChunkData() but renders each row through a Blade partial and
     * returns the JSON envelope the frontend appends. Rendering happens per
     * request (not cached) so role-gated action buttons reflect the current user.
     */
    protected function renderListChunk(
        string $domain,
        Builder $query,
        bool $cacheable,
        callable $formatRow,
        string $rowView,
        Request $request,
        int $perPage = self::LIST_PER_PAGE
    ): JsonResponse {
        $chunk = $this->listChunkData($domain, $query, $cacheable, $formatRow, $request, $perPage);

        $html = '';
        foreach ($chunk['rows'] as $row) {
            $html .= View::make($rowView, ['row' => $row])->render();
        }

        return $this->ok(['html' => $html, 'meta' => $chunk['meta']]);
    }

    /**
     * Fetch one page slice (with a +1 look-ahead to detect "has more") and map
     * each model to its row array.
     *
     * @return array{rows: array<int, array>, hasMore: bool}
     */
    private function sliceRows(Builder $query, int $page, int $perPage, callable $formatRow): array
    {
        $models = (clone $query)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        $hasMore = $models->count() > $perPage;

        return [
            'rows'    => $models->take($perPage)->map($formatRow)->values()->all(),
            'hasMore' => $hasMore,
        ];
    }

    /**
     * True when the request carries no search/filter/sort params — i.e. the
     * default, cacheable browse view. $filterKeys are the resource's own filter
     * query-param names (e.g. ['stock', 'wg']).
     *
     * @param  array<int, string> $filterKeys
     */
    protected function isDefaultListView(Request $request, array $filterKeys = []): bool
    {
        foreach (array_merge(['search', 'sort', 'dir'], $filterKeys) as $key) {
            if ($request->filled($key)) {
                return false;
            }
        }

        return true;
    }

    /** Normalise the ?dir param to a safe 'asc'/'desc'. */
    protected function sortDirection(Request $request): string
    {
        return strtolower((string) $request->query('dir')) === 'desc' ? 'desc' : 'asc';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Standardised JSON response helpers
    //
    // All API responses share the same envelope so the frontend only needs
    // one error-handling path:
    //
    //  Success  →  { "data": ..., "message": "..." }          2xx
    //  Created  →  { "data": ..., "message": "..." }          201
    //  Deleted  →  (empty body)                               204
    //  Error    →  { "message": "...", "errors": { ... } }    4xx / 5xx
    //
    // Laravel's own ValidationException already produces the errors shape,
    // so these helpers only need to cover the manual error cases.
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * 200 success with optional data payload.
     */
    protected function ok(mixed $data = null, string $message = ''): JsonResponse
    {
        $body = [];
        if ($data !== null)   $body['data']    = $data;
        if ($message !== '')  $body['message'] = $message;

        return response()->json($body, 200);
    }

    /**
     * 201 Created.
     */
    protected function created(mixed $data, string $message = 'Resource created successfully.'): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], 201);
    }

    /**
     * 204 No Content (delete).
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * 403 Forbidden.
     */
    protected function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return response()->json(['message' => $message], 403);
    }

    /**
     * 404 Not Found.
     */
    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return response()->json(['message' => $message], 404);
    }

    /**
     * 409 Conflict (e.g. FK constraint violation).
     */
    protected function conflict(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 409);
    }

    /**
     * 422 Unprocessable.
     *
     * @param  string|array  $errors  Either a plain message string or an
     *                                associative ['field' => ['msg']] map.
     */
    protected function unprocessable(string|array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        $body = ['message' => $message];

        if (is_string($errors)) {
            $body['errors'] = ['general' => [$errors]];
        } else {
            $body['errors'] = $errors;
        }

        return response()->json($body, 422);
    }

    /**
     * 500 Internal Server Error.
     */
    protected function serverError(string $message = 'An unexpected error occurred.'): JsonResponse
    {
        return response()->json(['message' => $message], 500);
    }

    /**
     * Convenience wrapper: catches ModelNotFoundException and returns 404,
     * re-throws everything else so the global handler picks it up.
     */
    protected function findOrNotFound(callable $query): mixed
    {
        try {
            return $query();
        } catch (ModelNotFoundException) {
            abort(response()->json(['message' => 'Resource not found.'], 404));
        }
    }
}