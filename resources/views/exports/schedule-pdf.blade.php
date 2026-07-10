<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Schedule Export</title>
    <style>
        * {
            margin: 5px;
            padding: 5px;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            background: #fff;
        }

        .header {
            background: #be1522;
            color: #fff;
            padding: 16px 20px;
            margin-bottom: 16px;
        }

        .header h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .header .meta {
            font-size: 10px;
            opacity: 0.8;
            margin-top: 4px;
        }

        .filters-row {
            margin-bottom: 14px;
            line-height: 2;
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

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-upcoming {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-completed {
            background: #e5e7eb;
            color: #374151;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .session-num {
            color: #6b7280;
            font-size: 9.5px;
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
        <h3>Schedule Report</h3>
        <div class="meta">{{ $generatedAt }}</div>
    </div>

    <div class="filters-row">
        @if (empty($activeFilters))
            <span class="filter-chip-all">All Sessions — No filters applied</span>
        @else
            @foreach ($activeFilters as $f)
                <span class="filter-chip">
                    <span class="chip-label">{{ $f['label'] }}:</span>{{ $f['value'] }}
                </span>
            @endforeach
        @endif
    </div>

    @if ($sessions->isEmpty())
        <p style="color:#6b7280; padding:20px 0;">No sessions found for the selected filters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Group</th>
                    <th>Course</th>
                    <th>Instructor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Students</th>
                    <th>Session</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><strong>{{ $row->group_name }}</strong></td>
                        <td>{{ $row->course_title }}</td>
                        <td>{{ $row->instructor_name }}</td>
                        <td>{{ $row->effective_date }}</td>
                        <td>
                            {{ $row->effective_start_time ? \Illuminate\Support\Str::substr($row->effective_start_time, 0, 5) : '—' }}
                            –
                            {{ $row->effective_end_time ? \Illuminate\Support\Str::substr($row->effective_end_time, 0, 5) : '—' }}
                        </td>
                        <td>{{ $row->room ?? '—' }}</td>
                        <td style="text-align:center;">{{ $row->student_count }}</td>
                        <td class="session-num" style="text-align:center;">
                            {{ $row->session_number }} / {{ $row->total_sessions }}
                        </td>
                        <td>
                            <span class="badge badge-{{ $row->status }}">{{ $row->status }}</span>
                            @if ($row->cancellation_reason)
                                <br><span style="font-size:8.5px;color:#9ca3af;">{{ $row->cancellation_reason }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">Total: {{ $sessions->count() }} sessions</div>
    @endif
</body>

</html>
