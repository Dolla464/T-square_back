<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\UploadAlreadyFinalizingException;
use App\Models\Course;
use App\Services\Admin\Upload\UploadSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * @tags Admin: Courses
 */
class ChunkedUploadController extends Controller
{
    public function __construct(
        private UploadSessionService $uploadSession,
    ) {}

    /**
     * Receive one video chunk and persist it to temporary storage.
     *
     * POST /api/admin/courses/{course}/previews/chunked-upload
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        try {
            $validated = $request->validate([
                'upload_id'         => ['required', 'uuid'],
                'chunk'             => ['required', 'file', 'max:' . config('upload.chunk_max_upload_kb')],
                'chunk_index'       => ['required', 'integer', 'min:0'],
                'total_chunks'      => ['required', 'integer', 'min:1'],
                'original_filename' => ['required', 'string', 'max:255'],
                'expected_filesize' => ['required', 'integer', 'min:1'],
                'sha256'            => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
                'preview_index'     => ['nullable', 'integer', 'min:0'],
                'chunk_size'        => ['nullable', 'integer', 'min:1'],
            ]);

            $result = $this->uploadSession->recordChunk(
                $course,
                $validated,
                $request->file('chunk'),
            );

            return response()->json($result);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Chunked upload store failed', [
                'course_id'   => $course->id,
                'upload_id'   => $request->input('upload_id'),
                'chunk_index' => $request->input('chunk_index'),
                'message'     => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Failed to store chunk on server.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal storage error.',
            ], 500);
        }
    }

    /**
     * Assemble previously uploaded chunks into the final video file.
     *
     * POST /api/admin/courses/{course}/previews/finalize-upload
     */
    public function finalize(Request $request, Course $course): JsonResponse
    {
        try {
            $validated = $request->validate([
                'upload_id'        => ['required', 'uuid'],
                'duration_seconds' => ['nullable', 'integer', 'min:0'],
            ]);

            $result = $this->uploadSession->finalize(
                $course,
                $validated['upload_id'],
                $validated['duration_seconds'] ?? null,
            );

            return response()->json($result);
        } catch (UploadAlreadyFinalizingException $e) {
            return response()->json([
                'error'   => 'Already finalizing.',
                'message' => $e->getMessage(),
            ], 409);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Chunked upload finalize failed', [
                'course_id' => $course->id,
                'upload_id' => $request->input('upload_id'),
                'message'   => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Failed to finalize upload.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal storage error.',
            ], 500);
        }
    }

    /**
     * Get upload session status (resume-ready).
     *
     * GET /api/admin/courses/{course}/previews/uploads/{upload_id}/status
     */
    public function status(Course $course, string $upload_id): JsonResponse
    {
        try {
            return response()->json(
                $this->uploadSession->getStatus($course, $upload_id)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'error'   => 'Upload session not found.',
                'message' => 'Upload session not found.',
            ], 404);
        }
    }
}
