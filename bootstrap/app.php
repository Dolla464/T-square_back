<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            //\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) { // لو الخطأ جاي من الـ API
                $statusCode = 500;
                $errors = null;

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $statusCode = $e->status;
                    $errors = $e->errors();
                } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $statusCode = 401;
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $statusCode = $e->getStatusCode();
                }

                $response = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];

                if ($errors) {
                    $response['errors'] = $errors;
                }

                // بنبعت التفاصيل دي بس في حالة الـ Debug
                if (config('app.debug')) {
                    $response['debug'] = $e->getTrace()[0];
                }

                return response()->json($response, $statusCode);
            }
        });
    })->create();
