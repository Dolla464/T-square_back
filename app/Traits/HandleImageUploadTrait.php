<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandleImageUploadTrait
{
    public function uploadImage(UploadedFile $file, string $folder, ?string $oldPath = null): string
    {
        // 1. حذف الصورة القديمة
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // 2. توليد الاسم الجديد بامتداد webp
        $generatedName = $folder . '_' . time() . '_' . Str::random(5) . '.webp';
        $fullPath = "{$folder}/{$generatedName}";

        // 3. معالجة الصورة وتحويلها (Native PHP)
        $imagePath = $file->getRealPath();
        
        // إنشاء مصدر الصورة بناءً على نوعها الأصلي
        $info = getimagesize($imagePath);
        $image = match($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG  => imagecreatefrompng($imagePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($imagePath),
            default        => throw new \Exception('نوع الصورة غير مدعوم للتحويل'),
        };

        // 4. استخدام Output Buffering لالتقاط بيانات WebP
        ob_start();
        imagewebp($image, null, 80); // جودة 80%
        $webpContent = ob_get_clean();
        imagedestroy($image);

        // 5. الحفظ باستخدام Storage
        Storage::disk('public')->put($fullPath, $webpContent);

        return $fullPath;
    }
}