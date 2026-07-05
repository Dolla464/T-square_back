<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AdminReviewCollection extends ResourceCollection
{
    public static $wrap = 'reviews';

    public $collects = AdminReviewResource::class;

    public function toArray(Request $request): array
    {
        return [
            'reviews' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
