<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Order;
use App\Observers\CourseObserver;
use App\Observers\CourseReviewObserver;
use App\Observers\EnrollmentObserve;
use App\Observers\OrderObserver;
use Carbon\Carbon;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        // لو إنت في بيئة الـ Local (أو أي بيئة تانية)، افرض الـ HTTPS
        // if (config('app.env') !== 'production') {
        //     URL::forceScheme('https');
        // }

        // Reset Password route
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // مراقبت جدول الenrollment
        Enrollment::observe(EnrollmentObserve::class);

        // vervification route
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $temporarySignedUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(60),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            $query = parse_url($temporarySignedUrl, PHP_URL_QUERY);

            return config('app.frontend_url').'/verify-email?'.$query.'&id='.$notifiable->getKey().'&hash='.sha1($notifiable->getEmailForVerification());
        });

        // Observers
        Course::observe(CourseObserver::class);
        Order::observe(OrderObserver::class);
        CourseReview::observe(CourseReviewObserver::class);

        // Scramble API Documentation
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'Bearer')
                );
            });
    }
}
