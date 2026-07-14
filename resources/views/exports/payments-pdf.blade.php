<!DOCTYPE html>
<html lang="en">

<head>
    @include('exports.partials.pdf-base-styles')
    <title>Payments Export</title>
    <style>
        * {
            margin: 5px;
            padding: 5px;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            background: #fff;
        }

        .header {
            background: #be1522;
            color: #fff;
            padding: 5px 5px;
            margin-bottom: 5px;
        }

        .header h1 {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .header-title-row {
            display: table;
            width: 100%;
        }

        .header-title-row h3,
        .header-title-row .meta {
            display: table-cell;
            vertical-align: middle;
            margin: 0;
            padding: 0;
        }

        .header-title-row h3 {
            text-align: left;
            font-size: 13px;
            font-weight: 600;
        }

        .header-title-row .meta {
            text-align: right;
            font-size: 10px;
            opacity: 0.9;
        }

        .filters-row {
            margin-bottom: 5px;
            /* line-height: 2; */
        }

        .filter-chip {
            display: inline-block;
            background: #e8f0fe;
            color: #1e3a5f;
            border: 2px solid #c5d8f8;
            border-radius: 5px;
            padding: 5px 5px;
            font-size: 10px;
        }

        .filter-chip .chip-label {
            font-weight: 700;
        }

        .filter-chip-all {
            display: inline-block;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 5px 5px;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }

        thead tr {
            background: #e8f0fe;
            color: #000;
            border: 1px solid #c5d8f8;
        }

        thead th {
            padding: 8px 6px;
            text-align: center;
            font-weight: 600;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) {
            background: #f4f7fb;
        }

        tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        tbody td {
            padding: 6px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        tfoot tr {
            background: #e8f0fe;
            font-weight: 700;
            border-top: 2px solid #c5d8f8;
        }

        tfoot td {
            padding: 8px 6px;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-refunded {
            background: #e5e7eb;
            color: #374151;
        }

        .footer {
            margin-top: 20px;
            font-size: 9px;
            color: #9ca3af;
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>T-Square LMS</h1>
        <div class="header-title-row">
            <h3>Orders / Payments Report</h3>
            <div class="meta">{{ $generatedAt }}</div>
        </div>
    </div>

    <div class="filters-row">
        @if (empty($activeFilters))
            <span class="filter-chip-all">All Payments — No filters applied</span>
        @else
            @foreach ($activeFilters as $f)
                <span class="filter-chip">
                    <span class="chip-label">{{ $f['label'] }}:</span>{{ $f['value'] }}
                </span>
            @endforeach
        @endif
    </div>

    @if ($orders->isEmpty())
        <p style="color:#6b7280; padding:20px 0;">No payment orders found for the selected filters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Student</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Amount (EGP)</th>
                    <th>Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    @php
                        $enrollment = $order->enrollments->first();
                        $courseTitle = $enrollment ? data_get($enrollment, 'course.title', '—') : '—';
                        $studentName = data_get($order, 'student.full_name') ?? ($order->billing_name ?? '—');
                        $email = data_get($order, 'student.user.email') ?? '—';
                    @endphp
                    <tr>
                        <td style="text-align:center;"><strong>#{{ $order->id }}</strong></td>
                        <td style="text-align:center;">{{ $studentName }}</td>
                        <td style="text-align:center;">{{ $email }}</td>
                        <td style="text-align:center;">{{ $courseTitle }}</td>
                        <td style="text-align:center;">{{ $order->total_amount }}</td>
                        <td style="text-align:center;">
                            <span class="badge badge-{{ $order->status }}">{{ $order->status }}</span>
                        </td>
                        <td style="text-align:center;">{{ $order->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            @php $totalAmount = $orders->sum('total_amount'); @endphp
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right;">Total</td>
                    <td style="text-align:center;">{{ number_format($totalAmount, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">Total: {{ $orders->count() }} orders</div>
    @endif
</body>

</html>
