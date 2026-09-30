<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * HTTP ENVELOPE — the API response contract.
 * Same shapes as src/lib/admin/http.ts:
 *   success: { success: true, data }
 *   error:   { success: false, error: { code, message, ...extra } }
 */
class ApiResponse
{
    public static function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    public static function error(int $status, string $code, string $message, array $extra = []): JsonResponse
    {
        return response()->json(
            ['success' => false, 'error' => array_merge(['code' => $code, 'message' => $message], $extra)],
            $status
        );
    }

    public static function badRequest(string $message = 'Malformed request.'): JsonResponse
    {
        return self::error(400, 'BAD_REQUEST', $message);
    }

    public static function unauthorized(string $message = 'Authentication required.'): JsonResponse
    {
        return self::error(401, 'UNAUTHORIZED', $message);
    }

    public static function forbidden(string $message = 'You do not have permission to perform this action.'): JsonResponse
    {
        return self::error(403, 'FORBIDDEN', $message);
    }

    public static function notFound(string $message = 'Not found.'): JsonResponse
    {
        return self::error(404, 'NOT_FOUND', $message);
    }

    /** 409 CONFLICT — e.g. deleting an inquiry whose meeting policy refuses (spec §7). */
    public static function conflict(string $message): JsonResponse
    {
        return self::error(409, 'CONFLICT', $message);
    }

    public static function validation(array $fields): JsonResponse
    {
        return self::error(422, 'VALIDATION_ERROR', 'Please correct the highlighted fields.', ['fields' => $fields]);
    }

    public static function rateLimited(): JsonResponse
    {
        return self::error(429, 'RATE_LIMITED', 'Too many requests. Please try again shortly.');
    }

    public static function server(): JsonResponse
    {
        return self::error(500, 'INTERNAL_ERROR', 'Something went wrong. Please try again.');
    }

    /**
     * CATCH-ALL EXCEPTION RENDERER (see bootstrap/app.php).
     *
     * Every unhandled failure on an api/* route — router 404s, verb mismatches,
     * throttle rejections, controller exceptions — must answer the standard
     * error envelope, never a Laravel HTML error page (the browser fetch()
     * client cannot parse those). Carried over from the security assessment:
     * an unhandled exception previously leaked the framework HTML page.
     *
     * Returns NULL for non-api requests so web routes keep the framework
     * default (prerendered pages, SPA shell, HTML error pages).
     */
    public static function forException(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        // Flows that already carry a response (FormRequest::failedValidation,
        // middleware aborts) pass through untouched.
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        if ($e instanceof ValidationException) {
            $fields = [];
            foreach ($e->errors() as $field => $messages) {
                $fields[$field] = $messages[0] ?? 'Invalid value.';
            }

            return self::validation($fields);
        }

        if ($e instanceof AuthenticationException) {
            return self::unauthorized();
        }

        if ($e instanceof ModelNotFoundException
            || ($e instanceof NotFoundHttpException && $e->getPrevious() instanceof ModelNotFoundException)) {
            return self::notFound('Resource not found.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return self::error($status, self::statusCodeFor($status), self::statusMessageFor($status))
                ->withHeaders($e->getHeaders());
        }

        // Unhandled Throwable: NEVER leak internals through the API — not even
        // in local debug mode (stack traces go to storage/logs, not the client).
        return self::server();
    }

    /** Stable machine codes for HTTP-level exceptions. */
    private static function statusCodeFor(int $status): string
    {
        return match (true) {
            $status === 400 => 'BAD_REQUEST',
            $status === 401 => 'UNAUTHORIZED',
            $status === 403 => 'FORBIDDEN',
            $status === 404 => 'NOT_FOUND',
            $status === 405 => 'METHOD_NOT_ALLOWED',
            $status === 408 => 'REQUEST_TIMEOUT',
            $status === 409 => 'CONFLICT',
            $status === 413 => 'PAYLOAD_TOO_LARGE',
            $status === 415 => 'UNSUPPORTED_MEDIA_TYPE',
            $status === 422 => 'VALIDATION_ERROR',
            $status === 429 => 'RATE_LIMITED',
            $status >= 500  => 'INTERNAL_ERROR',
            default         => 'REQUEST_FAILED',
        };
    }

    /** Generic, contract-stable messages for HTTP-level exceptions. */
    private static function statusMessageFor(int $status): string
    {
        return match (true) {
            $status === 400 => 'Malformed request.',
            $status === 401 => 'Authentication required.',
            $status === 403 => 'You do not have permission to perform this action.',
            $status === 404 => 'Not found.',
            $status === 405 => 'Method not allowed for this endpoint.',
            $status === 409 => 'Conflict.',
            $status === 413 => 'Request body too large.',
            $status === 422 => 'Please correct the highlighted fields.',
            $status === 429 => 'Too many requests. Please try again shortly.',
            $status >= 500  => 'Something went wrong. Please try again.',
            default         => 'Request failed.',
        };
    }
}
