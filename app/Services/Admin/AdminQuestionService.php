<?php

namespace App\Services\Admin;

use App\Models\Question;
use Illuminate\Support\Facades\DB;

class AdminQuestionService
{
    /**
     * Get questions by exam id with their choices.
     */
    public function getQuestionsByExam(int $examId)
    {
        return Question::where('exam_id', $examId)
            ->with('choices')
            ->latest()
            ->get();
    }

    /**
     * Create a new question with its choices in a single transaction
     */
    public function createQuestion(array $data): Question
    {
        return DB::transaction(function () use ($data) {
            // 1. Save the question first
            $question = Question::create([
                'exam_id'       => $data['exam_id'],
                'question_text' => $data['question_text'],
                'marks'         => $data['marks'],
            ]);

            // 2. Save the choices in a single transaction
            $question->choices()->createMany($data['choices']);

            return $question->load('choices');
        });
    }

    /**
     * Update the question and its choices
     */
    public function updateQuestion(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data) {
            // 1. Update the basic question data
            $question->update([
                'exam_id'       => $data['exam_id'],
                'question_text' => $data['question_text'],
                'marks'         => $data['marks'],
            ]);

            // 2. Delete the old choices and rebuild the new ones (easier and safer way to update)
            $question->choices()->forceDelete();
            // 3. Convert the choices to an explicit Array
            $choices = is_array($data['choices']) ? $data['choices'] : iterator_to_array($data['choices']);
            // 4. Create the new choices
            $question->choices()->createMany($choices);

            return $question->load('choices');
        });
    }

    /**
     * Delete a question (Soft Delete)
     */
    public function deleteQuestion(Question $question): bool
    {
        return $question->delete();
    }

    /**
     * Get trashed questions, optionally scoped to an exam.
     */
    public function getTrashedQuestions(?int $examId = null)
    {
        return Question::onlyTrashed()
            ->with('choices')
            ->when($examId, fn ($query) => $query->where('exam_id', $examId))
            ->latest()
            ->get();
    }

    /**
     * Restore a deleted question and its choices
     */
    public function restoreQuestion(int $id): Question
    {
        return DB::transaction(function () use ($id) {
            // Get the question even if it is deleted
            $question = Question::withTrashed()->findOrFail($id);

            // Restore the question
            $question->restore();

            // Restore the choices that are associated with it (if the Model Events are working, you will get it automatically, if not, this line is an additional security)
            $question->choices()->restore();

            return $question->load('choices');
        });
    }

    /**
     * Force delete a question and its choices from the database
     */
    public function forceDeleteQuestion(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $question = Question::withTrashed()->findOrFail($id);

            // Delete the choices first to ensure a clean deletion (or they will be deleted Cascade if not secured in the database)
            $question->choices()->forceDelete();

            // Delete the question finally
            return (bool) $question->forceDelete();
        });
    }
}
