<?php

namespace App\Providers;

use App\Events\StudentEnrolled;
use App\Events\StudentExamAttemptCompleted;
use App\Listeners\SendEnrollmentNotifications;
use App\Listeners\SendStudentExamAttemptNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        StudentEnrolled::class => [
            SendEnrollmentNotifications::class,
        ],
        StudentExamAttemptCompleted::class => [
            SendStudentExamAttemptNotification::class,
        ],
    ];
}
