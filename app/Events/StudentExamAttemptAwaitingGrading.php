<?php

namespace App\Events;

use App\Models\ExamAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentExamAttemptAwaitingGrading
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ExamAttempt $attempt,
    ) {}
}
