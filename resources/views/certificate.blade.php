<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Professional Fixed</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f0f0;
        }

        .certificate-container {
            width: 900px;
            height: 600px;
            background-color: white;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: hidden;
            box-sizing: border-box;
        }

        /* الخطوط العلوية والسفلية - بعيدة عن الدوائر */
        .header-line,
        .footer-line {
            position: absolute;
            width: 100%;
            display: flex;
            align-items: center;
            padding: 0 30px;
            box-sizing: border-box;
            z-index: 10;
        }

        .header-line {
            top: 30px;
            left: 0;
        }

        .footer-line {
            bottom: 30px;
            left: 0;
            flex-direction: row-reverse;
        }

        .red-bar {
            height: 10px;
            background-color: #c00000;
            flex-grow: 1;
        }

        .header-line .red-bar {
            border-radius: 0 10px 10px 0;
            margin-right: 20px;
        }

        .footer-line .red-bar {
            border-radius: 10px 0 0 10px;
            margin-left: 20px;
        }

        .dots {
            display: flex;
            gap: 15px;
        }

        .dot {
            width: 18px;
            height: 18px;
            background-color: #c00000;
            border-radius: 50%;
        }

        /* المحتوى يبدأ وينتهي بين الخطوط بدون لمس الدوائر */
        .main-content {
            width: 100%;
            height: 100%;
            padding: 70px 60px;
            /* مسافة لعدم لمس الخطوط والدواير */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            box-sizing: border-box;
        }

        .top-row {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            width: 110px;
            height: auto;
        }

        .center-name {
            font-size: 20px;
            color: #333;
        }

        .text-center {
            text-align: center;
        }

        .main-title {
            font-size: 45px;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 2px;
        }

        .certify-text {
            font-size: 18px;
            font-style: italic;
            margin-bottom: 20px;
        }

        .student-name {
            font-size: 34px;
            font-weight: bold;
            margin-bottom: 30px;
            border-bottom: none;
        }

        .course-info {
            font-size: 19px;
            margin-bottom: 5px;
        }

        .tags {
            font-size: 14px;
            color: #555;
            margin-bottom: 20px;
        }

        .date-text {
            font-size: 18px;
        }

        /* قسم التوقيعات حسب الصورة الموضحة */
        .signatures-area {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            /* لضمان محاذاة الأسماء تحت */
            position: relative;
            padding-bottom: 20px;
        }

        .sig-group {
            text-align: center;
            width: 200px;
        }

        /* الشرطة اللي في النص مرفوعة فوق شوية */
        .middle-dash {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 65px;
            /* مرفوعة عن مستوى الأسماء */
            font-weight: bold;
            font-size: 22px;
            letter-spacing: 1px;
        }

        .label {
            font-size: 16px;
            color: #777;
            margin-bottom: 5px;
        }

        .name {
            font-size: 19px;
            font-weight: bold;
            color: #000;
        }
    </style>
</head>

<body>

    <div class="certificate-container">
        <div class="header-line">
            <div class="red-bar"></div>
            <div class="dots">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
        </div>

        <div class="main-content">
            <div class="top-row">
                <img src="{{ asset('image/logo-dark.png') }}" alt="T-sqaure Logo" class="logo">
                <div class="center-name">T-Square Training Center</div>
            </div>

            <div class="text-center">
                <div class="main-title">CERTIFICATE</div>
                <div class="certify-text">This is to certify that</div>
                <div class="student-name">{{ $name }}</div>

                <div class="course-info">Has successfully completed course on {{ $course }}</div>
                <div class="tags">
                    (
                    @php
                        // Normalize tags: allow array/collection or string. Show a friendly fallback when missing.
                        $renderTags = null;
                        if (isset($tags)) {
                            if (is_string($tags)) {
                                $renderTags = $tags;
                            } elseif (is_array($tags) || $tags instanceof \Illuminate\Support\Collection) {
                                $renderTags = implode(' - ', (array) $tags);
                            }
                        }
                    @endphp

                    {{ $renderTags ?? '(No tags provided)' }}
                    )
                </div>

                <div class="date-text">Date Of Issue : {{ $date }}</div>
            </div>

            <div class="signatures-area">
                <div class="sig-group">
                    <div class="label">Instructor</div>
                    <div class="name">{{ $instructor_name ?? 'Instructor' }}</div>
                </div>

                <div class="middle-dash">---------</div>

                <div class="sig-group">
                    <div class="label">CEO</div>
                    <div class="name">Tamer Elshal</div>
                </div>
            </div>
        </div>

        <div class="footer-line">
            <div class="red-bar"></div>
            <div class="dots">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
        </div>
    </div>

</body>

</html>
