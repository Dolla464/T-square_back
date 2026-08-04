<?php

namespace App\Services\Admin;

use App\Models\Question;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminQuestionService
{
    public function getQuestionsByExam(int $examId)
    {
        return Question::where('exam_id', $examId)
            ->with('choices')
            ->latest()
            ->get();
    }

    public function createQuestion(array $data): Question
    {
        return DB::transaction(function () use ($data) {
            $question = Question::create($this->questionAttributes($data));

            $question->choices()->createMany($data['choices']);

            return $question->load('choices');
        });
    }

    public function updateQuestion(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data) {
            $this->deleteQuestionImageIfReplaced($question, $data['question_image'] ?? null);

            $question->update($this->questionAttributes($data));

            $choices = is_array($data['choices']) ? $data['choices'] : iterator_to_array($data['choices']);
            $this->syncChoices($question, $choices);

            return $question->load('choices');
        });
    }

    public function deleteQuestion(Question $question): bool
    {
        return $question->delete();
    }

    public function getTrashedQuestions(?int $examId = null)
    {
        return Question::onlyTrashed()
            ->with('choices')
            ->when($examId, fn ($query) => $query->where('exam_id', $examId))
            ->latest()
            ->get();
    }

    public function restoreQuestion(int $id): Question
    {
        return DB::transaction(function () use ($id) {
            $question = Question::withTrashed()->findOrFail($id);
            $question->restore();
            $question->choices()->restore();

            return $question->load('choices');
        });
    }

    public function forceDeleteQuestion(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $question = Question::withTrashed()->findOrFail($id);

            $this->deleteStoredQuestionImage($question->question_image);
            $question->choices()->forceDelete();

            return (bool) $question->forceDelete();
        });
    }

    public function uploadQuestionImage(UploadedFile $file): array
    {
        $path = $file->store('question-media', 'public');

        return [
            'path' => $path,
            'url' => asset('storage/'.$path),
        ];
    }

    private function syncChoices(Question $question, array $choices): void
    {
        if ($question->studentAnswers()->exists()) {
            $this->updateChoicesInPlace($question, $choices);

            return;
        }

        $question->choices()->forceDelete();
        $question->choices()->createMany($choices);
    }

    private function updateChoicesInPlace(Question $question, array $choices): void
    {
        $existingChoices = $question->choices()->orderBy('id')->get();

        if ($choices !== [] && count($choices) !== $existingChoices->count()) {
            throw ValidationException::withMessages([
                'choices' => 'Choice structure cannot be changed after students have answered this question.',
            ]);
        }

        foreach ($choices as $index => $choiceData) {
            if (! $existingChoices->has($index)) {
                throw ValidationException::withMessages([
                    'choices' => 'Choice structure cannot be changed after students have answered this question.',
                ]);
            }

            $existingChoices[$index]->update([
                'choice_text' => $choiceData['choice_text'],
                'is_correct' => $choiceData['is_correct'],
            ]);
        }
    }

    private function questionAttributes(array $data): array
    {
        return [
            'exam_id' => $data['exam_id'],
            'question_text' => filled(trim((string) ($data['question_text'] ?? '')))
                ? trim((string) $data['question_text'])
                : null,
            'question_image' => filled($data['question_image'] ?? null)
                ? (string) $data['question_image']
                : null,
            'question_code' => filled(trim((string) ($data['question_code'] ?? '')))
                ? trim((string) $data['question_code'])
                : null,
            'question_code_language' => filled(trim((string) ($data['question_code'] ?? '')))
                ? ($data['question_code_language'] ?? null)
                : null,
            'marks' => $data['marks'],
        ];
    }

    private function deleteQuestionImageIfReplaced(Question $question, ?string $newPath): void
    {
        $currentPath = $question->question_image;

        if (! $currentPath) {
            return;
        }

        if ($newPath === null || $newPath === '' || $newPath !== $currentPath) {
            $this->deleteStoredQuestionImage($currentPath);
        }
    }

    private function deleteStoredQuestionImage(?string $path): void
    {
        if (! $path || filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
