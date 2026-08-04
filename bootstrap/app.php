<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\ValidateAttendanceDevice;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CheckMaintenanceMode::class);
        $middleware->api(prepend: [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'attendance.device' => ValidateAttendanceDevice::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 1. Create a dummy class (Anonymous Class) to use the trait
        $responder = new class
        {
            use ApiResponseTrait;
        };

        // 2. Intercept errors if the request is coming from the API path
        $exceptions->render(function (Throwable $e, Request $request) use ($responder) {

            if ($request->is('api/*') || $request->wantsJson()) {

                // Error 422: Validation failed
                if ($e instanceof ValidationException) {
                    $errors = $e->errors();
                    $firstError = collect($errors)->flatten()->first();

                    return $responder->errorResponse($firstError, 422, $errors);
                }

                // Error 404: The record not found in the database (like when you search for a verified course)
                if ($e instanceof ModelNotFoundException) {
                    return $responder->structuredErrorResponse(
                        error: 'Resource not found',
                        code: 'NOT_FOUND',
                        httpCode: 404,
                    );
                }

                // Error 404: The same link is wrong or not found
                if ($e instanceof NotFoundHttpException) {
                    return $responder->structuredErrorResponse(
                        error: 'Resource not found',
                        code: 'NOT_FOUND',
                        httpCode: 404,
                        message: 'This route does not exist',
                    );
                }

                // Error 401: The user is not logged in (Token is wrong or expired)
                if ($e instanceof AuthenticationException) {
                    return $responder->errorResponse('Unauthenticated access', 401);
                }

                // Error 403: The user is logged in but does not have permission for this action
                if ($e instanceof AccessDeniedHttpException) {
                    return $responder->structuredErrorResponse(
                        error: 'Unauthorized access',
                        code: 'FORBIDDEN',
                        httpCode: 403,
                    );
                }

                // Error 429: Too many requests (rate limiting)
                if ($e instanceof TooManyRequestsHttpException) {
                    return $responder->errorResponse(
                        $e->getMessage() ?: 'Too many requests. Please try again later.',
                        429
                    );
                }

                // abort() and other HTTP exceptions (403, 422, 503, etc.)
                if ($e instanceof HttpException) {
                    return $responder->errorResponse(
                        $e->getMessage() ?: 'Request failed',
                        $e->getStatusCode()
                    );
                }

                // Error 500: Any other programming error on the server
                $message = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();

                return $responder->errorResponse($message, 500);
            }
        });
    })->create();
