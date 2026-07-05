<?php

namespace App\Http\Controllers\Api\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Services\Admin\AdminScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * @tags Receptionist: Schedule
 *
 * Read-only centre-wide schedule view with filtering and export.
 * No reschedule or cancel actions are available to receptionists.
 */
class ReceptionistScheduleController extends Controller
{
    public function __construct(
        private AdminScheduleService $scheduleService
    ) {}

    /**
     * GET /api/receptionist/schedule
     *
     * Same filter params as the admin schedule endpoint.
     * Reuses AdminScheduleService — no write access.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['date', 'date_from', 'date_to', 'instructor_id', 'status', 'group_id', 'view_mode']);
        $perPage = (int) $request->get('per_page', 15);

        $sessions = $this->scheduleService->getAllSessions($filters, $perPage);

        return $this->paginateResponse($sessions, 'Schedule retrieved successfully');
    }

    /**
     * GET /api/receptionist/schedule/export
     *
     * Same export logic as admin. Returns base64-encoded PDF or CSV payload.
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

    /**
     * GET /api/receptionist/instructors
     *
     * Slim list of instructors (id + full_name) for the schedule filter dropdown.
     */
    public function instructors(Request $request): JsonResponse
    {
        $instructors = Instructor::select('id', 'full_name')
            ->orderBy('full_name')
            ->get();

        return $this->successResponse($instructors, 'Instructors retrieved successfully');
    }

    // ── Private helpers (mirrored from AdminScheduleController) ─────────────

    private function exportPdf($sessions, array $filters): JsonResponse
    {
        $pdf = Pdf::loadView('exports.schedule-pdf', [
            'sessions'      => $sessions,
            'activeFilters' => $this->buildActiveFilters($filters),
            'generatedAt'   => now()->format('Y-m-d H:i'),
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
