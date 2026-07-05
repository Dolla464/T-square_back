<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Default user notification channel (used by Laravel notifications broadcast)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Attendance Channels
|--------------------------------------------------------------------------
|
| private-instructor.{id}  — The instructor real-time attendance dashboard.
|   Receives StudentScanned events whenever a student registers attendance
|   in any session owned by this instructor (via hardware scan or app check-in).
|
| Clients subscribe with:  Echo.private(`instructor.${instructorId}`)
*/
Broadcast::channel('instructor.{instructorId}', function ($user, $instructorId) {
    $instructor = $user->instructor;

    return $instructor && (int) $instructor->id === (int) $instructorId;
});
