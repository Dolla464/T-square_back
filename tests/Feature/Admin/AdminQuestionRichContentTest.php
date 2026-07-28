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

    $this->exam = Exam::factory()->create([
        'course_id' => Course::factory()->create([
            'status' => 'published',
            'published_at' => now(),
        ])->id,
    ]);
});

function validQuestionPayload(int $examId, array $overrides = []): array
{
    return array_merge([
        'exam_id' => $examId,
        'question_text' => 'What is Laravel?',
        'marks' => 1,
        'choices' => [
            ['choice_text' => 'A PHP framework', 'is_correct' => true],
            ['choice_text' => 'A database', 'is_correct' => false],
        ],
    ], $overrides);
}

it('creates a text-only question', function (): void {
    $response = $this->postJson('/api/admin/questions', validQuestionPayload($this->exam->id));

    $response->assertCreated()
        ->assertJsonPath('data.question_text', 'What is Laravel?');

    $this->assertDatabaseHas('questions', [
        'exam_id' => $this->exam->id,
        'question_text' => 'What is Laravel?',
    ]);
});

it('creates a code-only question', function (): void {
    $response = $this->postJson('/api/admin/questions', validQuestionPayload($this->exam->id, [
        'question_text' => null,
        'question_code' => 'echo "Hello";',
        'question_code_language' => 'php',
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.question_code', 'echo "Hello";')
        ->assertJsonPath('data.question_code_language', 'php');
});

it('rejects a question without any content', function (): void {
    $response = $this->postJson('/api/admin/questions', validQuestionPayload($this->exam->id, [
        'question_text' => null,
        'question_image' => null,
        'question_code' => null,
    ]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['question_text']);
});

it('uploads a question image and stores the path', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('diagram.png');

    $response = $this->postJson('/api/admin/questions/upload-image', [
        'image' => $file,
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['path', 'url']]);

    Storage::disk('public')->assertExists($response->json('data.path'));
});

it('returns rich question fields in admin resource', function (): void {
    $question = Question::create([
        'exam_id' => $this->exam->id,
        'question_text' => 'Review this snippet',
        'question_code' => 'const x = 1;',
        'question_code_language' => 'javascript',
        'marks' => 2,
    ]);

    $question->choices()->createMany([
        ['choice_text' => 'One', 'is_correct' => true],
        ['choice_text' => 'Two', 'is_correct' => false],
    ]);

    $response = $this->getJson("/api/admin/questions/{$question->id}");

    $response->assertOk()
        ->assertJsonPath('data.question_code', 'const x = 1;')
        ->assertJsonPath('data.question_code_language', 'javascript');
});
