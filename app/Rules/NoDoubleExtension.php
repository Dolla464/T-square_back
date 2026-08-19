<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class NoDoubleExtension implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $name = strtolower($value->getClientOriginalName());

        if (preg_match('/\.(php|phtml|phar|js|html|htaccess|exe|sh|bat)\./', $name)) {
            $fail('The uploaded file name is not allowed.');
        }
    }
}
