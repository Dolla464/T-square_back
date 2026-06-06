<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Professional Fixed</title>

    <style>
        /* حقن ملف الـ CSS المحسن المكتمل */
        {!! file_get_contents(resource_path('css/pdf/certificate.css')) !!}
    </style>
</head>

<body>

    @php
        // 1. تحويل العلامة المائية إلى بيانت باينري مدمجة
        $watermarkPath = public_path('image/logo-watermark.png');
        $watermarkData = is_file($watermarkPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($watermarkPath))
            : null;

        // 2. تحويل الشعار العلوي لـ Base64 لتجنب مشاكل التزامن واختفاء الشعار
        $logoPath = public_path('image/logo-dark.png');
        $logoData = is_file($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    @endphp

    <div class="certificate-container">

        @if ($watermarkData)
            <div class="watermark-layer">
                <img src="{{ $watermarkData }}" alt="" class="watermark-image">
            </div>
        @endif

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
                @if ($logoData)
                    <img src="{{ $logoData }}" alt="T-square Logo" class="logo">
                @else
                    <div style="width: 145px;"></div>
                @endif
                <div class="center-name">T-Square Training Center</div>
            </div>

            <div class="text-center">
                <div class="main-title">CERTIFICATE</div>
                <div class="certify-text">This is to certify that</div>
                <div class="student-name">{{ $name }}</div>

                <div class="course-info">Has successfully completed course on {{ $course }}</div>

                @php
                    $renderTags = null;
                    if (isset($tags) && !empty($tags)) {
                        if (is_string($tags)) {
                            $renderTags = $tags;
                        } elseif (is_array($tags) || $tags instanceof \Illuminate\Support\Collection) {
                            $renderTags = implode(' - ', (array) $tags);
                        }
                    }
                @endphp

                @if ($renderTags)
                    <div class="tags">({{ $renderTags }})</div>
                @endif

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