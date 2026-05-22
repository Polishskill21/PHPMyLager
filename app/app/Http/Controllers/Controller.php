<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class Controller
{
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
     * 422 Unprocessable — mirrors Laravel's ValidationException envelope
     * so the frontend sees a consistent shape for both rule-based and
     * business-logic validation failures.
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
     * 500 Internal Server Error — never expose raw exception messages in
     * production; pass them through Laravel's logging instead.
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