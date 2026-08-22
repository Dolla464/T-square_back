<?php

namespace App\Services\Video;

use App\Exceptions\ExpiredPlaybackTokenException;
use App\Exceptions\InvalidPlaybackTokenException;
use Illuminate\Support\Facades\Crypt;

class PlaybackTokenService
{
    private const TTL_MINUTES = 10;

    public function issue(int $userId, int $lessonId, int $courseId): array
    {
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        $payload = [
            'user_id' => $userId,
            'lesson_id' => $lessonId,
            'course_id' => $courseId,
            'exp' => $expiresAt->timestamp,
        ];

        return [
            'token' => Crypt::encryptString(json_encode($payload)),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @return array{user_id: int, lesson_id: int, course_id: int, exp: int}
     */
    public function verify(string $token, int $userId, int $lessonId, int $courseId): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            throw new InvalidPlaybackTokenException;
        }

        if (! is_array($payload)) {
            throw new InvalidPlaybackTokenException;
        }

        if (($payload['exp'] ?? 0) < now()->timestamp) {
            throw new ExpiredPlaybackTokenException;
        }

        if ((int) ($payload['user_id'] ?? 0) !== $userId
            || (int) ($payload['lesson_id'] ?? 0) !== $lessonId
            || (int) ($payload['course_id'] ?? 0) !== $courseId) {
            throw new InvalidPlaybackTokenException;
        }

        return $payload;
    }
}
