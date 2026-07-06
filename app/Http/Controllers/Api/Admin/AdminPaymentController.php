<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Payment\StorePaymentRequest;
use App\Http\Requests\Api\Admin\Payment\UpdatePaymentRequest;
use App\Http\Resources\Admin\Payment\AdminPaymentCollection;
use App\Http\Resources\Admin\Payment\AdminPaymentResource;
use App\Models\Order;
use App\Services\Admin\AdminPaymentAnalyticsService;
use App\Services\Admin\AdminPaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * @tags Admin: Payments
 */
class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly AdminPaymentService $payments,
        private readonly AdminPaymentAnalyticsService $analytics
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AdminPaymentCollection
    {
        $validated = $request->validate([
            'search'    => 'nullable|string|max:255',
            'status'    => 'nullable|string|in:pending,completed,cancelled,refunded',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'per_page'  => 'nullable|integer|min:1|max:100',
            'page'      => 'nullable|integer|min:1',
        ]);

        $perPage = max(1, min(100, (int) ($validated['per_page'] ?? 10)));

        $filters = [
            'search'    => $validated['search'] ?? '',
            'status'    => $validated['status'] ?? '',
            'date_from' => $validated['date_from'] ?? '',
            'date_to'   => $validated['date_to'] ?? '',
        ];

        $paginator = $this->payments->index($filters, $perPage);

        $stats = $this->analytics->getRecentStats(
            $filters['date_from'] ?: null,
            $filters['date_to'] ?: null,
        );

        return new AdminPaymentCollection($paginator, $stats);
    }

    /**
     * GET /api/admin/payments/export
     *
     * Query params: same filters as index + format=pdf|excel
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'    => 'nullable|string|max:255',
            'status'    => 'nullable|string|in:pending,completed,cancelled,refunded',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'format'    => 'nullable|string|in:pdf,excel',
        ]);

        $filters = [
            'search'    => $validated['search'] ?? '',
            'status'    => $validated['status'] ?? '',
            'date_from' => $validated['date_from'] ?? '',
            'date_to'   => $validated['date_to'] ?? '',
        ];

        $format = $validated['format'] ?? 'pdf';
        $orders = $this->payments->getOrdersForExport($filters);

        if ($format === 'excel') {
            return $this->exportExcel($orders);
        }

        return $this->exportPdf($orders, $filters);
    }

    /**
     * Store a newly created order + enrollment in storage.
     */
    public function store(StorePaymentRequest $request): \Illuminate\Http\JsonResponse
    {
        $order = $this->payments->store($request->validated());

        return (new AdminPaymentResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): AdminPaymentResource
    {
        $order = $this->payments->show($id);

        return new AdminPaymentResource($order);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentRequest $request, int $id): AdminPaymentResource
    {
        $order = $this->payments->update($id, $request->validated());

        return new AdminPaymentResource($order);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): Response
    {
        $this->payments->destroy($id);

        return response()->noContent();
    }

    // ── Export helpers ───────────────────────────────────────────────────────

    private function exportPdf(Collection $orders, array $filters): JsonResponse
    {
        $pdf = Pdf::loadView('exports.payments-pdf', [
            'orders'        => $orders,
            'activeFilters' => $this->buildActiveFilters($filters),
            'generatedAt'   => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'payments-' . now()->format('Ymd') . '.pdf';

        return $this->successResponse([
            'content'  => base64_encode($pdf->output()),
            'filename' => $filename,
            'mime'     => 'application/pdf',
        ], 'PDF export ready');
    }

    private function exportExcel(Collection $orders): JsonResponse
    {
        $filename = 'payments-' . now()->format('Ymd') . '.csv';

        ob_start();
        $handle = fopen('php://output', 'w');

        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Order ID', 'Student', 'Email', 'Course', 'Amount (EGP)',
            'Status', 'Created At',
        ]);

        foreach ($orders as $order) {
            fputcsv($handle, [
                $order->id,
                data_get($order, 'student.full_name') ?? $order->billing_name ?? '',
                data_get($order, 'student.user.email') ?? '',
                $this->courseTitle($order),
                $order->total_amount,
                $order->status,
                $order->created_at?->format('Y-m-d H:i') ?? '',
            ]);
        }

        if ($orders->isNotEmpty()) {
            $totalAmount = $orders->sum('total_amount');
            fputcsv($handle, ['', '', '', 'Total', number_format((float) $totalAmount, 2, '.', ''), '', '']);
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
     * @return array<int, array{label: string, value: string}>
     */
    private function buildActiveFilters(array $filters): array
    {
        $active = [];

        if (! empty($filters['search'])) {
            $active[] = ['label' => 'Search', 'value' => $filters['search']];
        }

        if (! empty($filters['status'])) {
            $active[] = ['label' => 'Status', 'value' => ucfirst($filters['status'])];
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $active[] = [
                'label' => 'Period',
                'value' => $filters['date_from'] . ' → ' . $filters['date_to'],
            ];
        } elseif (! empty($filters['date_from'])) {
            $active[] = ['label' => 'From', 'value' => $filters['date_from']];
        } elseif (! empty($filters['date_to'])) {
            $active[] = ['label' => 'To', 'value' => $filters['date_to']];
        }

        return $active;
    }

    private function courseTitle(Order $order): string
    {
        $enrollment = $order->enrollments->first();

        return $enrollment ? (string) data_get($enrollment, 'course.title', '') : '';
    }
}
