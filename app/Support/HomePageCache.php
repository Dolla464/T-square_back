<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class HomePageCache
{
    public const KEY = 'public_home_page_data';

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
