<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                    'debug' => config('app.debug') ? $e->getTrace()[0] ?? null : null,
                ], 422);
            }

            if ($e instanceof ModelNotFoundException) {
                $modelName = class_basename($e->getModel());
                $modelLabel = strtolower($modelName);
                $missingId = $e->getIds()[0] ?? null;
                $message = $missingId !== null
                    ? "No {$modelLabel} found with id {$missingId}."
                    : "No {$modelLabel} found.";

                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                    'debug' => config('app.debug') ? $e->getTrace()[0] ?? null : null,
                ], 404);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Endpoint not found.',
                    'debug' => config('app.debug') ? $e->getTrace()[0] ?? null : null,
                ], 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'HTTP method not allowed for this endpoint.',
                    'debug' => config('app.debug') ? $e->getTrace()[0] ?? null : null,
                ], 405);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                    'debug' => config('app.debug') ? $e->getTrace()[0] ?? null : null,
                ], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to perform this action.',
                    'debug' => config('app.debug') ? $e->getTrace()[0] ?? null : null,
                ], 403);
            }

            if ($e instanceof ThrottleRequestsException) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Too many requests. Please try again later.',
                    'debug' => config('app.debug') ? $e->getTrace()[0] ?? null : null,
                ], 429);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected server error occurred.',
                'debug' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        });
    })->create();
