<!DOCTYPE html>
<html lang="en">

<head>
    @include('exports.partials.pdf-base-styles')
    <title>Attendance Matrix Export</title>
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

        .header-title-row > div {
            display: table-cell;
            vertical-align: middle;
            margin: 0;
            padding: 0;
        }

        .header-title-row h3,
        .header-title-row .meta {
            margin: 0;
            padding: 0;
        }

        .header-title-row h3 {
            text-align: left;
            font-size: 13px;
            font-weight: 600;
        }

        .header-title-row .course-title {
            text-align: left;
            font-size: 11px;
            font-weight: 400;
            opacity: 0.95;
            margin-top: 2px;
        }

        .header-title-row .meta {
            text-align: right;
            font-size: 10px;
            opacity: 0.9;
        }

        table {
            width: auto;
            border-collapse: collapse;
            font-size: 9px;
        }

        .col-index {
            width: 28px;
            text-align: center;
        }

        .student-name-col {
            text-align: left;
            white-space: nowrap;
            width: 1%;
        }

        thead tr {
            background: #e8f0fe;
            color: #000;
            border: 1px solid #c5d8f8;
        }

        thead th {
            padding: 6px 4px;
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
            padding: 5px 4px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            text-align: center;
        }

        .student-name {
            text-align: left;
            white-space: nowrap;
        }

        .session-col {
            width: 40px;
            min-width: 28px;
            max-width: 50px;
            padding: 4px 2px;
            font-size: 8px;
            text-align: center;
        }

        .status-icon {
            font-family: 'DejaVu Sans', sans-serif;
            font-weight: 700;
            font-size: 10px;
        }

        .pdf-page-break {
            page-break-before: always;
        }

        .chunk-subtitle {
            font-size: 9px;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .status-present {
            color: #065f46;
        }

        .status-absent {
            color: #991b1b;
        }

        .status-late {
            color: rgb(255, 193, 7);
        }

        .status-not_marked {
            color: #6b7280;
        }

        .status-excused {
            color: #1e40af;
        }

        .status-cancelled {
            color: #9ca3af;
        }

        .summary-col {
            min-width: 52px;
            font-weight: 700;
            text-align: center;
        }

        .footer {
            margin-top: 20px;
            font-size: 9px;
            color: #9ca3af;
            text-align: right;
        }

        .legend {
            margin-top: 10px;
            font-size: 9px;
            color: #374151;
            font-family: 'DejaVu Sans', sans-serif;
        }

        .legend span {
            margin-right: 12px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>T-Square LMS</h1>
        <div class="header-title-row">
            <div>
                <h3>COURSE : {{ $payload['course_title'] }}</h3>
                @if (!empty($payload['course_title']))
                    <div class="course-title">GROUP : {{ $payload['group_name'] ?? '—' }}</div>
                @endif
            </div>
            <div class="meta">{{ $generatedAt }}</div>
        </div>
    </div>

    @php
        $sessions = $payload['sessions'] ?? [];
        $students = $payload['students'] ?? [];

        $formatSessionHeader = function (?string $date): string {
            if (! $date) {
                return '—';
            }

            try {
                return \Carbon\Carbon::parse($date)->format('M j');
            } catch (\Exception $e) {
                return $date;
            }
        };

        $statusSymbolHtml = function (string $status): string {
            return match ($status) {
                'present' => '<span class="status-icon status-present">&#10003;</span>',
                'absent' => '<span class="status-icon status-absent">&#10007;</span>',
                'late' => '<span class="status-icon status-late">#</span>',
                'excused' => '<span class="status-icon status-excused">E</span>',
                'cancelled' => '<span class="status-icon status-cancelled">-</span>',
                default => '<span class="status-icon status-not_marked">.</span>',
            };
        };

        $sessionsPerPage = 15;
        $sessionChunks = array_chunk($sessions, $sessionsPerPage);
        $totalSessions = count($sessions);
        $totalPages = count($sessionChunks);
    @endphp

    @if (empty($students))
        <p style="color:#6b7280; padding:20px 0;">No students enrolled in this group.</p>
    @elseif (empty($sessions))
        <p style="color:#6b7280; padding:20px 0;">No sessions found for this group.</p>
    @else
        @foreach ($sessionChunks as $chunkIndex => $sessionChunk)
            @if ($chunkIndex > 0)
                <div class="pdf-page-break"></div>
            @endif

            @php
                $sessionFrom = ($chunkIndex * $sessionsPerPage) + 1;
                $sessionTo = min(($chunkIndex + 1) * $sessionsPerPage, $totalSessions);
                $isLastChunk = $chunkIndex === $totalPages - 1;
            @endphp

            @if ($totalPages > 1)
                <div class="chunk-subtitle">
                    Sessions {{ $sessionFrom }}&ndash;{{ $sessionTo }} of {{ $totalSessions }}
                </div>
            @endif

            <table>
                <thead>
                    <tr>
                        <th class="col-index">#</th>
                        <th class="student-name-col">Student Name</th>
                        @foreach ($sessionChunk as $session)
                            <th class="session-col">{{ $formatSessionHeader($session['session_date'] ?? null) }}</th>
                        @endforeach
                        @if ($isLastChunk)
                            <th class="summary-col">Total Present</th>
                            <th class="summary-col">Total Absences</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $idx => $student)
                        <tr>
                            <td class="col-index">{{ $idx + 1 }}</td>
                            <td class="student-name-col">{{ $student['full_name'] ?? '—' }}</td>
                            @foreach ($sessionChunk as $session)
                                @php
                                    $status = $student['statuses'][(string) $session['id']] ?? 'not_marked';
                                @endphp
                                <td class="session-col">
                                    {!! $statusSymbolHtml($status) !!}
                                </td>
                            @endforeach
                            @if ($isLastChunk)
                                <td class="summary-col">
                                    <span class="status-present">{{ $student['attended_sessions'] ?? 0 }}</span>
                                </td>
                                <td class="summary-col">
                                    <span class="status-absent">{{ $student['absent_sessions'] ?? 0 }}</span>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="legend">
                <span class="status-present">&#10003; Present</span>
                <span class="status-absent">&#10007; Absent</span>
                <span class="status-late"># Late</span>
                <span class="status-not_marked">. Not Marked</span>
                <span class="status-cancelled">- Cancelled</span>
            </div>

            <div class="footer">
                @if ($totalPages > 1)
                    Page {{ $chunkIndex + 1 }} of {{ $totalPages }} |
                    Sessions {{ $sessionFrom }}&ndash;{{ $sessionTo }} of {{ $totalSessions }} |
                @endif
                Total: {{ count($students) }} students | {{ $totalSessions }} sessions
            </div>
        @endforeach
    @endif
</body>

</html>
