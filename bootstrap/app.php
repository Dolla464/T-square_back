<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureEmailIsVerified;
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
                    // This line gets the model name to tell you (Course not found for example)
                    $modelName = class_basename($e->getModel());

                    return $responder->errorResponse("Sorry, this model ($modelName) not found", 404);
                }

                // Error 404: The same link is wrong or not found
                if ($e instanceof NotFoundHttpException) {
                    return $responder->errorResponse('This route does not exist', 404);
                }

                // Error 401: The user is not logged in (Token is wrong or expired)
                if ($e instanceof AuthenticationException) {
                    return $responder->errorResponse('Unauthenticated access', 401);
                }

                // Error 403: The user is logged in but does not have permission for this action
                if ($e instanceof AccessDeniedHttpException) {
                    return $responder->errorResponse('Unauthorized access', 403);
                }

                // ── The new and special modification ──
                // Error 503: The site is in maintenance mode (the API)
                if ($e instanceof HttpException && $e->getStatusCode() === 503) {
                    return $responder->errorResponse($e->getMessage(), 503);
                }

                // Error 500: Any other programming error on the server (like forgetting a letter or error in the database)
                // In development mode, it will return the real error message, in production you can make it a fixed message
                $message = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();

                return $responder->errorResponse($message, 500);
            }
        });
    })->create();
