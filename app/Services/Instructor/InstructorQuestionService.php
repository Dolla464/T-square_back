<?php

namespace App\Services\Instructor;

use App\Models\Question;
use App\Services\Admin\AdminQuestionService;

class InstructorQuestionService
{
    public function __construct(private AdminQuestionService $adminQuestionService)
    {}

    public function getQuestionsByExam(int $examId)
    {
        return $this->adminQuestionService->getQuestionsByExam($examId);
    }

    public function createQuestion(array $data, ?int $updatedByUserId = null): Question
    {
        return $this->adminQuestionService->createQuestion($data, $updatedByUserId);
    }

    public function updateQuestion(Question $question, array $data, ?int $updatedByUserId = null): Question
    {
        return $this->adminQuestionService->updateQuestion($question, $data, $updatedByUserId);
    }

    public function deleteQuestion(Question $question, ?int $updatedByUserId = null): bool
    {
        return $this->adminQuestionService->deleteQuestion($question, $updatedByUserId);
    }

    public function getTrashedQuestions(int $examId)
    {
        return $this->adminQuestionService->getTrashedQuestions($examId);
    }

    public function restoreQuestion(int $id): Question
    {
        return $this->adminQuestionService->restoreQuestion($id);
    }

    public function forceDeleteQuestion(int $id): bool
    {
        return $this->adminQuestionService->forceDeleteQuestion($id);
    }

    public function uploadQuestionImage($file): array
    {
        return $this->adminQuestionService->uploadQuestionImage($file);
    }
}
