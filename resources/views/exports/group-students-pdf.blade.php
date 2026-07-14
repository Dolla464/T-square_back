<!DOCTYPE html>
<html lang="en">

<head>
    @include('exports.partials.pdf-base-styles')
    <title>Group Students Export</title>
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

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-progress {
            background: #fef3c7;
            color: #92400e;
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
            <h3>Group Students Report</h3>
            <div class="meta">{{ $generatedAt }}</div>
        </div>
    </div>

    <div class="filters-row">
        <span class="filter-chip">
            <span class="chip-label">Group:</span>{{ $group->group_name ?? '—' }}
        </span>
        <span class="filter-chip">
            <span class="chip-label">Course:</span>{{ data_get($group, 'course.title', '—') }}
        </span>
        <span class="filter-chip">
            <span class="chip-label">Instructor:</span>{{ data_get($group, 'instructor.full_name', '—') }}
        </span>
    </div>

    @if ($students->isEmpty())
        <p style="color:#6b7280; padding:20px 0;">No students enrolled in this group.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $idx => $student)
                    <tr>
                        <td style="text-align:center;">{{ $idx + 1 }}</td>
                        <td>{{ $student->full_name ?? '—' }}</td>
                        <td>{{ $student->email ?? '—' }}</td>
                        <td style="text-align:center;">{{ $student->phone ?? '—' }}</td>
                        <td style="text-align:center;">
                            @if ($student->is_completed)
                                <span class="badge badge-completed">Completed</span>
                            @else
                                <span class="badge badge-progress">In Progress</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">Total: {{ $students->count() }} students</div>
    @endif
</body>

</html>
