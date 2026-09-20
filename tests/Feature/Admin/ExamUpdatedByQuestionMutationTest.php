<?php

use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);
    Sanctum::actingAs($this->admin, ['*']);

    $this->otherUser = User::factory()->create();

    $this->exam = Exam::factory()->create([
        'course_id' => Course::factory()->create([
            'status' => 'published',
            'published_at' => now(),
        ])->id,
        'updated_by' => null,
        'updated_at' => now()->subDay(),
    ]);

    $this->originalUpdatedAt = $this->exam->updated_at;
});

function questionPayload(int $examId, array $overrides = []): array
{
    return array_merge([
        'exam_id' => $examId,
        'type' => 'mcq',
        'question_text' => 'Sample question?',
        'marks' => 1,
        'choices' => [
            ['choice_text' => 'Correct', 'is_correct' => true],
            ['choice_text' => 'Wrong', 'is_correct' => false],
        ],
    ], $overrides);
}

function createQuestionForExam(Exam $exam): Question
{
    $question = Question::create([
        'exam_id' => $exam->id,
        'type' => Question::TYPE_MCQ,
        'question_text' => 'Existing question?',
        'marks' => 1,
    ]);

    $question->choices()->createMany([
        ['choice_text' => 'A', 'is_correct' => true],
        ['choice_text' => 'B', 'is_correct' => false],
    ]);

    return $question;
}

it('records exam updated_by and updated_at when creating a question', function (): void {
    $response = $this->postJson('/api/admin/questions', questionPayload($this->exam->id));

    $response->assertCreated();

    $this->exam->refresh();

    expect($this->exam->updated_by)->toBe($this->admin->id)
        ->and($this->exam->updated_at->gt($this->originalUpdatedAt))->toBeTrue();
});

it('records exam updated_by and updated_at when updating a question and choices', function (): void {
    $question = createQuestionForExam($this->exam);

    $this->exam->update([
        'updated_by' => null,
        'updated_at' => now()->subDay(),
    ]);
    $this->exam->refresh();
    $baselineUpdatedAt = $this->exam->updated_at;

    $response = $this->putJson("/api/admin/questions/{$question->id}", questionPayload($this->exam->id, [
        'question_text' => 'Updated question?',
        'choices' => [
            ['choice_text' => 'New A', 'is_correct' => true],
            ['choice_text' => 'New B', 'is_correct' => false],
        ],
    ]));

    $response->assertOk();

    $this->exam->refresh();

    expect($this->exam->updated_by)->toBe($this->admin->id)
        ->and($this->exam->updated_at->gt($baselineUpdatedAt))->toBeTrue();
});

it('records exam updated_by and updated_at when soft deleting a question', function (): void {
    $question = createQuestionForExam($this->exam);

    $this->exam->update([
        'updated_by' => null,
        'updated_at' => now()->subDay(),
    ]);
    $this->exam->refresh();
    $baselineUpdatedAt = $this->exam->updated_at;

    $response = $this->deleteJson("/api/admin/questions/{$question->id}");

    $response->assertOk();

    $this->exam->refresh();

    expect($this->exam->updated_by)->toBe($this->admin->id)
        ->and($this->exam->updated_at->gt($baselineUpdatedAt))->toBeTrue();
});

it('does not change exam last updated fields when restoring a question', function (): void {
    $question = createQuestionForExam($this->exam);
    $question->delete();

    $this->exam->update([
        'updated_by' => $this->otherUser->id,
        'updated_at' => now()->subHours(2),
    ]);
    $this->exam->refresh();
    $baselineUpdatedBy = $this->exam->updated_by;
    $baselineUpdatedAt = $this->exam->updated_at->toDateTimeString();

    $response = $this->postJson("/api/admin/questions/{$question->id}/restore");

    $response->assertOk();

    $this->exam->refresh();

    expect($this->exam->updated_by)->toBe($baselineUpdatedBy)
        ->and($this->exam->updated_at->toDateTimeString())->toBe($baselineUpdatedAt);
});

it('does not change exam last updated fields when force deleting a question', function (): void {
    $question = createQuestionForExam($this->exam);
    $question->delete();

    $this->exam->update([
        'updated_by' => $this->otherUser->id,
        'updated_at' => now()->subHours(2),
    ]);
    $this->exam->refresh();
    $baselineUpdatedBy = $this->exam->updated_by;
    $baselineUpdatedAt = $this->exam->updated_at->toDateTimeString();

    $response = $this->deleteJson("/api/admin/questions/{$question->id}/force-delete");

    $response->assertOk();

    $this->exam->refresh();

    expect($this->exam->updated_by)->toBe($baselineUpdatedBy)
        ->and($this->exam->updated_at->toDateTimeString())->toBe($baselineUpdatedAt);
});

it('does not change exam last updated fields when uploading a question image', function (): void {
    Storage::fake('public');

    $this->exam->update([
        'updated_by' => $this->otherUser->id,
        'updated_at' => now()->subHours(2),
    ]);
    $this->exam->refresh();
    $baselineUpdatedBy = $this->exam->updated_by;
    $baselineUpdatedAt = $this->exam->updated_at->toDateTimeString();

    $response = $this->postJson('/api/admin/questions/upload-image', [
        'image' => UploadedFile::fake()->image('diagram.png'),
    ]);

    $response->assertCreated();

    $this->exam->refresh();

    expect($this->exam->updated_by)->toBe($baselineUpdatedBy)
        ->and($this->exam->updated_at->toDateTimeString())->toBe($baselineUpdatedAt);
});

it('ignores updated_by sent in question request body', function (): void {
    $response = $this->postJson('/api/admin/questions', array_merge(questionPayload($this->exam->id), [
        'updated_by' => $this->otherUser->id,
    ]));

    $response->assertCreated();

    $this->exam->refresh();

    expect($this->exam->updated_by)->toBe($this->admin->id)
        ->and($this->exam->updated_by)->not->toBe($this->otherUser->id);
});
