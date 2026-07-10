<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Exam Results Export</title>
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

        .header h3 {
            font-size: 13px;
            font-weight: 600;
            margin-top: 4px;
        }

        .header .meta {
            font-size: 10px;
            opacity: 0.8;
            margin-top: 4px;
        }

        .info-row {
            margin-bottom: 14px;
            line-height: 2;
        }

        .info-chip {
            display: inline-block;
            background: #e8f0fe;
            color: #1e3a5f;
            border: 2px solid #c5d8f8;
            border-radius: 5px;
            padding: 5px 8px;
            font-size: 10px;
            margin-right: 6px;
        }

        .info-chip .chip-label {
            font-weight: 700;
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

        .badge-passed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-none {
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
        <h3>Exam Results Report</h3>
        <div class="meta">{{ $generatedAt }}</div>
    </div>

    <div class="info-row">
        <span class="info-chip"><span class="chip-label">Group:</span>{{ $payload['group_name'] ?? '—' }}</span>
        <span class="info-chip"><span class="chip-label">Course:</span>{{ $payload['course_title'] ?? '—' }}</span>
        <span class="info-chip"><span class="chip-label">Exam:</span>{{ $payload['exam_title'] ?? '—' }}</span>
        <span class="info-chip"><span class="chip-label">Total Marks:</span>{{ $payload['total_marks'] ?? '—' }}</span>
        <span class="info-chip"><span class="chip-label">Passing
                Mark:</span>{{ $payload['passing_mark'] ?? '—' }}</span>
    </div>

    @php
        $students = $payload['students'] ?? [];
        $totalMarks = $payload['total_marks'] ?? null;
    @endphp

    @if (empty($students))
        <p style="color:#6b7280; padding:20px 0;">No students enrolled in this group.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Attempts</th>
                    <th>Highest Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $idx => $student)
                    @php
                        $hasAttempts = $student['has_attempts'] ?? false;
                        if ($hasAttempts) {
                            $highest = $student['highest_score'] ?? null;
                            $scoreDisplay =
                                $highest !== null && $totalMarks !== null
                                    ? $highest . ' / ' . $totalMarks
                                    : $highest ?? '—';
                            $status = $student['is_passed'] ?? false ? 'Passed' : 'Failed';
                            $badgeClass = $student['is_passed'] ?? false ? 'badge-passed' : 'badge-failed';
                        } else {
                            $scoreDisplay = '—';
                            $status = 'No attempts';
                            $badgeClass = 'badge-none';
                        }
                    @endphp
                    <tr>
                        <td style="text-align:center;">{{ $idx + 1 }}</td>
                        <td>{{ $student['full_name'] ?? '—' }}</td>
                        <td>{{ $student['email'] ?? '—' }}</td>
                        <td style="text-align:center;">{{ $student['attempts_count'] ?? 0 }}</td>
                        <td style="text-align:center;">{{ $scoreDisplay }}</td>
                        <td style="text-align:center;">
                            <span class="badge {{ $badgeClass }}">{{ $status }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            Total: {{ count($students) }} students
        </div>
    @endif
</body>

</html>
