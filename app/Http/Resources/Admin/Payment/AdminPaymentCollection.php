<?php

namespace App\Http\Resources\Admin\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AdminPaymentCollection extends ResourceCollection
{
   // بنعرف متغير عشان نستقبل فيه الـ Stats من الكنترولر
    protected $stats;

    public function __construct($resource, $stats)
    {
        parent::__construct($resource);
        $this->stats = $stats;
    }

    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'stats'   => $this->stats, // الإحصائيات المكيشة هتنزل هنا فوق
            'orders'  => $this->collection, // هنا الـ لارافيل تلقائياً هيعدي كل Order على الـ AdminPaymentResource ويحوله لـ Array
        ];
    }
}
