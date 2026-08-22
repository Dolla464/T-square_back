<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use App\Services\Video\LessonAuthorizationService;

class LessonPolicy
{
    public function __construct(
        private readonly LessonAuthorizationService $authorizationService,
    ) {}

    public function view(User $user, Lesson $lesson): bool
    {
        return $this->authorizationService->authorize($user, $lesson)->isAllowed();
    }
}
