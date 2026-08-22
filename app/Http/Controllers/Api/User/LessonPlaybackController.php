<?php

namespace App\Http\Controllers\Api\User;

use App\Exceptions\ExpiredPlaybackTokenException;
use App\Exceptions\GoogleAccountDisconnectedException;
use App\Exceptions\GoogleDriveFileAccessException;
use App\Exceptions\InvalidPlaybackTokenException;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\Google\GoogleDriveStreamingService;
use App\Services\Video\LessonAuthorizationService;
use App\Services\Video\PlaybackTokenService;
use App\Services\Video\VideoActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonPlaybackController extends Controller
{
    public function __construct(
        private readonly LessonAuthorizationService $authorizationService,
        private readonly PlaybackTokenService $playbackTokenService,
        private readonly GoogleDriveStreamingService $streamingService,
        private readonly VideoActivityLogger $videoLogger,
    ) {}

    public function authorizePlayback(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $result = $this->authorizationService->authorize($user, $lesson);

        if (! $result->isAllowed()) {
            $this->videoLogger->log('video_access_denied', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'course_id' => $lesson->course_id,
                'reason' => $result->getMessage(),
            ]);

            return $this->structuredErrorResponse(
                $result->getMessage() ?? 'Access denied.',
                $result->getCode(),
                $result->getStatusCode()
            );
        }

        $lesson->loadMissing('course.googleStorageAccount');
        $tokenData = $this->playbackTokenService->issue(
            $user->id,
            $lesson->id,
            $lesson->course_id
        );

        $streamUrl = url("/api/student/lessons/{$lesson->id}/stream?token=".urlencode($tokenData['token']));

        $student = Student::where('user_id', $user->id)->first();

        $this->videoLogger->log('video_play_authorized', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'storage_account_id' => $lesson->course?->google_storage_account_id,
        ]);

        return $this->successResponse([
            'stream_url' => $streamUrl,
            'expires_at' => $tokenData['expires_at'],
            'watermark' => [
                'name' => $student?->full_name ?? $user->name,
                'student_number' => $student?->id,
            ],
        ], 'Playback authorized successfully.');
    }

    public function stream(Request $request, Lesson $lesson)
    {
        $user = $request->user();
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            $this->videoLogger->log('invalid_playback_token', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'course_id' => $lesson->course_id,
            ]);

            return $this->structuredErrorResponse(
                'Invalid playback token.',
                'FORBIDDEN',
                403
            );
        }

        try {
            $this->playbackTokenService->verify(
                $token,
                $user->id,
                $lesson->id,
                $lesson->course_id
            );
        } catch (ExpiredPlaybackTokenException) {
            $this->videoLogger->log('expired_playback_token', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'course_id' => $lesson->course_id,
            ]);

            return $this->structuredErrorResponse(
                'Playback token has expired.',
                'FORBIDDEN',
                403
            );
        } catch (InvalidPlaybackTokenException) {
            $this->videoLogger->log('invalid_playback_token', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'course_id' => $lesson->course_id,
            ]);

            return $this->structuredErrorResponse(
                'Invalid playback token.',
                'FORBIDDEN',
                403
            );
        }

        $result = $this->authorizationService->authorize($user, $lesson);

        if (! $result->isAllowed()) {
            return $this->structuredErrorResponse(
                'You are not allowed to watch this lesson.',
                'FORBIDDEN',
                403
            );
        }

        $lesson->loadMissing('course.googleStorageAccount');
        $account = $lesson->course?->googleStorageAccount;

        if (! $account) {
            return $this->structuredErrorResponse(
                'Video is unavailable right now.',
                'UNPROCESSABLE',
                422
            );
        }

        try {
            $opened = $this->streamingService->openStream(
                $account,
                $lesson->google_drive_file_id,
                $request->header('Range')
            );

            $this->videoLogger->log('video_play_started', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'course_id' => $lesson->course_id,
                'storage_account_id' => $account->id,
            ]);

            return $this->streamingService->streamResponse($opened);
        } catch (GoogleAccountDisconnectedException) {
            return $this->structuredErrorResponse(
                'Unable to play video right now. Please try again later.',
                'UNPROCESSABLE',
                422
            );
        } catch (GoogleDriveFileAccessException) {
            return $this->structuredErrorResponse(
                'Video is unavailable right now.',
                'UNPROCESSABLE',
                422
            );
        }
    }
}
