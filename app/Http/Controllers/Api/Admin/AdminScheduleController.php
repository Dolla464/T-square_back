<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Services\Admin\AdminScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * @tags Admin: Schedule
 *
 * Provides a centre-wide schedule view with filtering, session management,
 * and PDF / Excel export capabilities.
 */
class AdminScheduleController extends Controller
{
    public function __construct(
        private AdminScheduleService $scheduleService
    ) {}

    /**
     * GET /api/admin/schedule
     *
     * Query params:
     *   date           string  Y-m-d  filter by effective session date (day mode)
     *   date_from      string  Y-m-d  range start (week Sat or month 1st)
     *   date_to        string  Y-m-d  range end (week Fri or month last day)
     *   view_mode      string         day|week|month — used for export labels only
     *   instructor_id  int            filter by instructor
     *   status         string         filter by session status
     *   group_id       int            filter by group
     *   per_page       int    default 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['date', 'date_from', 'date_to', 'instructor_id', 'status', 'group_id', 'view_mode']);
        $perPage = (int) $request->get('per_page', 15);

        $sessions = $this->scheduleService->getAllSessions($filters, $perPage);

        return $this->paginateResponse($sessions, 'Schedule retrieved successfully');
    }

    /**
     * PUT /api/admin/schedule/{session}
     *
     * Body: { date: "Y-m-d", start_time: "H:i", end_time: "H:i" }
     */
    public function reschedule(Request $request, AttendanceSession $session): JsonResponse
    {
        if ($session->status === 'cancelled') {
            return $this->errorResponse('Cannot reschedule a cancelled session.', 422);
        }

        $validated = $request->validate([
            'date'       => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
        ]);

        $session = $this->scheduleService->rescheduleSession($session, $validated);

        return $this->successResponse($session, 'Session rescheduled successfully. Notifications have been sent.');
    }

    /**
     * DELETE /api/admin/schedule/{session}
     *
     * Body (optional): { reason: "string" }
     */
    public function cancel(Request $request, AttendanceSession $session): JsonResponse
    {
        if ($session->status === 'cancelled') {
            return $this->errorResponse('Session is already cancelled.', 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $session = $this->scheduleService->cancelSession($session, $validated['reason'] ?? null);

        return $this->successResponse($session, 'Session cancelled successfully. Notifications have been sent.');
    }

    /**
     * GET /api/admin/schedule/export
     *
     * Returns the file as a base64-encoded JSON payload so that:
     *   1. Laravel CORS middleware can add its headers (not stripped by streaming).
     *   2. Browser extensions like IDM do not intercept the XHR request.
     *
     * Query params: same filters as index + format=pdf|excel
     */
    public function export(Request $request): JsonResponse
    {
        $filters = $request->only(['date', 'date_from', 'date_to', 'instructor_id', 'status', 'group_id', 'view_mode']);
        $format  = $request->get('format', 'pdf');

        $sessions = $this->scheduleService->getSessionsForExport($filters);

        if ($format === 'excel') {
            return $this->exportExcel($sessions);
        }

        return $this->exportPdf($sessions, $filters);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function exportPdf($sessions, array $filters): JsonResponse
    {
        $pdf = Pdf::loadView('exports.schedule-pdf', [
            'sessions'       => $sessions,
            'activeFilters'  => $this->buildActiveFilters($filters),
            'generatedAt'    => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'schedule-' . now()->format('Ymd') . '.pdf';

        return $this->successResponse([
            'content'  => base64_encode($pdf->output()),
            'filename' => $filename,
            'mime'     => 'application/pdf',
        ], 'PDF export ready');
    }

    private function exportExcel($sessions): JsonResponse
    {
        $filename = 'schedule-' . now()->format('Ymd') . '.csv';

        ob_start();
        $handle = fopen('php://output', 'w');

        // UTF-8 BOM so Excel opens the CSV correctly
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Group Name', 'Course', 'Instructor', 'Date',
            'Start Time', 'End Time', 'Room', 'Students',
            'Session No.', 'Total Sessions', 'Status', 'Cancellation Reason',
        ]);

        foreach ($sessions as $row) {
            fputcsv($handle, [
                $row->group_name,
                $row->course_title,
                $row->instructor_name,
                $row->effective_date,
                $row->effective_start_time ? substr($row->effective_start_time, 0, 5) : '',
                $row->effective_end_time   ? substr($row->effective_end_time, 0, 5)   : '',
                $row->room ?? '',
                $row->student_count,
                $row->session_number . ' / ' . $row->total_sessions,
                $row->total_sessions,
                $row->status,
                $row->cancellation_reason ?? '',
            ]);
        }

        fclose($handle);
        $content = ob_get_clean();

        return $this->successResponse([
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'text/csv',
        ], 'CSV export ready');
    }

    /**
     * Resolve filter values into human-readable label/value pairs for the PDF view.
     * Returns an array of ['label' => ..., 'value' => ...] items.
     */
    private function buildActiveFilters(array $filters): array
    {
        $active = [];

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $rangeLabel = ($filters['view_mode'] ?? '') === 'month' ? 'Month' : 'Week';
            $active[] = [
                'label' => $rangeLabel,
                'value' => $filters['date_from'] . ' → ' . $filters['date_to'],
            ];
        } elseif (!empty($filters['date'])) {
            $active[] = ['label' => 'Date', 'value' => $filters['date']];
        }

        if (!empty($filters['instructor_id'])) {
            $name = \Illuminate\Support\Facades\DB::table('instructors')
                ->where('id', $filters['instructor_id'])
                ->value('full_name');
            $active[] = ['label' => 'Instructor', 'value' => $name ?? ('ID ' . $filters['instructor_id'])];
        }

        if (!empty($filters['status'])) {
            $active[] = ['label' => 'Status', 'value' => ucfirst($filters['status'])];
        }

        if (!empty($filters['group_id'])) {
            $name = \Illuminate\Support\Facades\DB::table('learning_groups')
                ->where('id', $filters['group_id'])
                ->value('group_name');
            $active[] = ['label' => 'Group', 'value' => $name ?? ('ID ' . $filters['group_id'])];
        }

        return $active;
    }
}
