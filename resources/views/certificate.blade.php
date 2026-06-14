<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Certificate</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            background: white;
            margin: 0;
            padding: 0;
        }

        .page-wrapper {
            padding: 20px 32px 18px;
            page-break-inside: avoid;
        }

        .top-red-line {
            background-color: #c00000;
            height: 7px;
            width: 100%;
            margin-bottom: 14px;
        }

        .bottom-red-line {
            background-color: #c00000;
            height: 7px;
            width: 100%;
            margin-top: 14px;
        }

        .logo-row {
            text-align: left;
            margin-bottom: 12px;
        }

        .logo {
            width: 155px;
            height: auto;
        }

        .center-title {
            text-align: center;
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .certificate-big {
            text-align: center;
            font-size: 38px;
            font-weight: bold;
            color: #8B0000;
            letter-spacing: 4px;
            margin: 6px 0 10px;
        }

        .certify-text {
            text-align: center;
            font-size: 15px;
            margin-bottom: 10px;
        }

        .student-name {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .student-tags {
            text-align: center;
            font-size: 13px;
            color: #444;
            margin-bottom: 10px;
        }

        .course-info {
            text-align: center;
            font-size: 15px;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .course-name {
            font-weight: bold;
            font-size: 17px;
        }

        .date-issue {
            text-align: center;
            font-size: 14px;
            margin-bottom: 14px;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .signatures-table td {
            text-align: center;
            width: 50%;
            padding: 0 40px;
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 80%;
            margin: 0 auto;
        }

        .signature-name {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }

        .signature-title {
            font-size: 11px;
            color: #777;
            margin-top: 3px;
        }
    </style>
</head>

<body>
    <div class="page-wrapper">

        <div class="top-red-line"></div>

        <div class="logo-row">
            @if ($logoData)
                <img src="{{ $logoData }}" class="logo">
            @endif
        </div>

        <div class="center-title">T-Square Training Center</div>

        <div class="certificate-big">CERTIFICATE</div>

        <div class="certify-text">This is to certify that</div>

        <div class="student-name">{{ $name }}</div>

        <div class="course-info">
            Has successfully completed course on
            <span class="course-name">{{ $course }}</span>
            @if (!empty($tags))
                <div class="student-tags">({{ is_array($tags) ? implode(' - ', $tags) : $tags }})</div>
            @endif
        </div>

        <div class="date-issue">Date Of Issue : {{ $date }}</div>

        <table class="signatures-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $instructor_name ?? 'Instructor' }}</div>
                    <div class="signature-title">Instructor</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-name">Tamer Elshal</div>
                    <div class="signature-title">CEO</div>
                </td>
            </tr>
        </table>

        <div class="bottom-red-line"></div>

    </div>
</body>

</html>
