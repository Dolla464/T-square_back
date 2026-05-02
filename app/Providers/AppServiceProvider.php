<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\CourseReview::observe(\App\Observers\CourseReviewObserver::class);
    }
}
