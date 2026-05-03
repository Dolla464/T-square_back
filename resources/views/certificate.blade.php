<!DOCTYPE html>
<html>

<head>
    <title>Certificate</title>
    <style>
        @import url('https://fonts.googleapis.com/css?family=Open+Sans|Pinyon+Script|Rochester');

        @page {
            size: A4 landscape;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            background: #fff;
            /* خليه أبيض عشان الطباعة */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .pm-certificate-container {
            position: relative;
            width: 297mm;
            /* عرض الـ A4 landscape */
            height: 210mm;
            /* طول الـ A4 landscape */
            background-color: #618597;
            padding: 0;
            color: #333;
            font-family: 'Open Sans', sans-serif;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* تعديل البوردر عشان يظبط مع أبعاد الورقة */
        .outer-border {
            position: absolute;
            top: 10mm;
            left: 10mm;
            right: 10mm;
            bottom: 10mm;
            border: 2px solid #fff;
            pointer-events: none;
        }

        .inner-border {
            position: absolute;
            top: 20mm;
            left: 20mm;
            right: 20mm;
            bottom: 20mm;
            border: 2px solid #fff;
            pointer-events: none;
        }

        .pm-certificate-border {
            position: absolute;
            top: 25mm;
            left: 25mm;
            right: 25mm;
            bottom: 25mm;
            background-color: rgba(255, 255, 255, 1);
            padding: 40px;
            text-align: center;
        }

        /* باقي التنسيقات خليها زي ما هي بس شيل الـ positions المعقدة */
        .cursive {
            font-family: 'Pinyon Script', cursive;
        }

        .bold {
            font-weight: bold;
        }

        .underline {
            border-bottom: 1px solid #777;
            padding-bottom: 5px;
            margin-bottom: 15px;
            display: inline-block;
            min-width: 200px;
        }

        .pm-certificate-title h2 {
            font-size: 34px;
            margin-top: 20px;
        }

        .pm-name-text {
            font-size: 30px;
        }

        .pm-earned-text {
            font-size: 20px;
            display: block;
            margin: 10px 0;
        }

        .pm-credits-text {
            font-size: 18px;
            display: block;
        }

        .pm-certificate-footer {
            position: absolute;
            bottom: 40px;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: space-around;
        }

        .footer-item {
            width: 30%;
        }
    </style>
</head>

<body>
    <div class="pm-certificate-container">
        <div class="outer-border"></div>
        <div class="inner-border"></div>
        <div class="pm-certificate-border">
            <div class="pm-certificate-title cursive">
                <h2>Course completion certificate from T-square Academy</h2>
            </div>

            <div class="pm-certificate-body">
                <br><br>
                <div class="underline">
                    <span class="pm-name-text bold">{{ $name }}</span>
                </div>
                <span class="pm-earned-text padding-0 block cursive">is hereby awarded this</span>
                <span class="pm-credits-text block bold sans">Certificate of Completion</span>
                <br>
                <span class="pm-earned-text cursive">while completing the training course entitled</span>
                <div class="underline">
                    <span class="pm-credits-text bold">{{ $course }}</span>
                </div>
            </div>

            <div class="pm-certificate-footer">
                <div class="footer-item">
                    <span class="bold">T-square</span>
                    <div style="border-top: 1px solid #777; margin: 10px 0;"></div>
                    <small>Staff Development</small>
                </div>
                <div class="footer-item">
                    <span class="bold">Date Completed</span>
                    <div style="border-top: 1px solid #777; margin: 10px 0;"></div>
                    <small>{{ $date }}</small>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
