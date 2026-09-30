<?php

/*
|--------------------------------------------------------------------------
| EXCEPTION → ENVELOPE MAPPING (unit contract)
|--------------------------------------------------------------------------
| Pins ApiResponse::forException()'s mapping table: status codes, machine
| codes, messages, header passthrough, api/* scoping and the pass-through
| semantics for responses that already exist.
*/

use App\Services\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

function apiRequest(): Request
{
    return Request::create('/api/anything', 'GET');
}

function webRequest(): Request
{
    return Request::create('/anything', 'GET');
}

it('maps every http-level exception to its pinned status, code and message', function (Throwable $e, int $status, string $code, string $message) {
    $response = ApiResponse::forException($e, apiRequest());

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe($status)
        ->and($response->getData(true))
        ->toEqual([
            'success' => false,
            'error'   => ['code' => $code, 'message' => $message],
        ]);
})->with([
    '400 bad request'      => [new BadRequestHttpException('x'), 400, 'BAD_REQUEST', 'Malformed request.'],
    '404 router not found' => [new NotFoundHttpException(), 404, 'NOT_FOUND', 'Not found.'],
    '403 access denied'    => [new AccessDeniedHttpException(), 403, 'FORBIDDEN', 'You do not have permission to perform this action.'],
    '409 conflict'         => [new ConflictHttpException('x'), 409, 'CONFLICT', 'Conflict.'],
    '429 throttle'         => [new ThrottleRequestsException(new TooManyRequestsHttpException(60, 'x')), 429, 'RATE_LIMITED', 'Too many requests. Please try again shortly.'],
    '500 http exception'   => [new HttpException(500), 500, 'INTERNAL_ERROR', 'Something went wrong. Please try again.'],
    'unhandled throwable'  => [new RuntimeException('secret-should-not-appear'), 500, 'INTERNAL_ERROR', 'Something went wrong. Please try again.'],
]);

it('returns null for non-api requests so web routes keep framework behavior', function () {
    expect(ApiResponse::forException(new RuntimeException('nope'), webRequest()))->toBeNull()
        ->and(ApiResponse::forException(new NotFoundHttpException(), webRequest()))->toBeNull();
});

it('passes through responses that already exist (HttpResponseException)', function () {
    $envelope = ApiResponse::validation(['email' => 'Enter a valid email address.']);
    $e = new \Illuminate\Http\Exceptions\HttpResponseException($envelope);

    expect(ApiResponse::forException($e, apiRequest()))->toBe($envelope);
});

it('maps authentication exceptions to the 401 envelope', function () {
    $response = ApiResponse::forException(new AuthenticationException(), apiRequest());

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'error'   => ['code' => 'UNAUTHORIZED', 'message' => 'Authentication required.'],
        ]);
});

it('maps validation exceptions to the VALIDATION_ERROR envelope with first message per field', function () {
    $validator = validator([], ['email' => 'required|email']);
    $e = new ValidationException($validator);

    $response = ApiResponse::forException($e, apiRequest());

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'error'   => [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'Please correct the highlighted fields.',
                'fields'  => ['email' => 'The email field is required.'],
            ],
        ]);
});

it('maps model-not-found (direct and nested) to the 404 envelope', function () {
    $direct = ApiResponse::forException(new ModelNotFoundException(), apiRequest());
    expect($direct->getStatusCode())->toBe(404)
        ->and($direct->getData(true)['error']['code'])->toBe('NOT_FOUND')
        ->and($direct->getData(true)['error']['message'])->toBe('Resource not found.');

    $nested = ApiResponse::forException(new NotFoundHttpException('x', new ModelNotFoundException()), apiRequest());
    expect($nested->getStatusCode())->toBe(404)
        ->and($nested->getData(true)['error']['code'])->toBe('NOT_FOUND')
        ->and($nested->getData(true)['error']['message'])->toBe('Resource not found.');
});

it('preserves http-exception headers (Allow on 405, Retry-After on 429)', function () {
    $method = ApiResponse::forException(new \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException(['POST'], 'x'), apiRequest());
    expect($method->getStatusCode())->toBe(405)
        ->and($method->headers->get('Allow'))->toBe('POST')
        ->and($method->getData(true)['error']['code'])->toBe('METHOD_NOT_ALLOWED');

    $throttle = ApiResponse::forException(new TooManyRequestsHttpException(30, 'x'), apiRequest());
    expect($throttle->getStatusCode())->toBe(429)
        ->and((int) $throttle->headers->get('Retry-After'))->toBe(30);
});
