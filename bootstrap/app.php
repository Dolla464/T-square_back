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

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
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
        // 1. إنشاء كلاس وهمي (Anonymous Class) لاستخدام التريت
        $responder = new class
        {
            use ApiResponseTrait;
        };

        // 2. اعتراض الأخطاء لو الطلب جاي من مسار API
        $exceptions->render(function (Throwable $e, Request $request) use ($responder) {

            if ($request->is('api/*') || $request->wantsJson()) {

                // خطأ 422: فشل التحقق من البيانات (Validation)
                if ($e instanceof ValidationException) {
                    $errors = $e->errors();
                    $firstError = collect($errors)->flatten()->first();

                    return $responder->errorResponse($firstError, 422, $errors);
                }

                // خطأ 404: السجل غير موجود في الداتابيز (زي لما تبحث عن كورس ممسوح)
                if ($e instanceof ModelNotFoundException) {
                    // السطر ده بيجيب اسم الموديل عشان يقولك (Course غير موجود مثلاً)
                    $modelName = class_basename($e->getModel());

                    return $responder->errorResponse("Sorry, this model ($modelName) not found", 404);
                }

                // خطأ 404: الرابط نفسه غلط أو مش موجود
                if ($e instanceof NotFoundHttpException) {
                    return $responder->errorResponse('This route does not exist', 404);
                }

                // خطأ 401: المستخدم مش مسجل دخول (Token غلط أو منتهي)
                if ($e instanceof AuthenticationException) {
                    return $responder->errorResponse('Unauthenticated access', 401);
                }

                // خطأ 403: المستخدم مسجل دخول بس معندوش صلاحية للأكشن ده
                if ($e instanceof AccessDeniedHttpException) {
                    return $responder->errorResponse('Unauthorized access', 403);
                }

                // خطأ 500: أي خطأ برمجي تاني في السيرفر (زي نسيان حرف أو خطأ في الداتابيز)
                // في وضع التطوير هيرجعلك رسالة الخطأ الحقيقية، في الإنتاج تقدر تخليها رسالة ثابتة
                $message = config('app.debug') ? $e->getMessage() : 'Server error';

                return $responder->errorResponse($message, 500);
            }

        });
    })->create();
