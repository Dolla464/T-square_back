<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AdminReviewCollection extends ResourceCollection
{
    protected $stats;

    public function __construct($resource, $stats)
    {
        parent::__construct($resource);
        $this->stats = $stats;
    }

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'stats'   => $this->stats,   // الإحصائيات فوق
            'reviews' => $this->collection, // المراجعات تحت
        ];
    }
}