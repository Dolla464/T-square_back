<?php

namespace App\Http\Resources\Admin\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AdminPaymentCollection extends ResourceCollection
{
   // بنعرف متغير عشان نستقبل فيه الـ Stats من الكنترولر
    protected $stats;

    public function __construct($resource, $stats = null)
    {
        parent::__construct($resource);
        $this->stats = $stats;
    }

    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'success' => true,
            'orders'  => $this->collection,
        ];

        if ($this->stats !== null) {
            $payload['stats'] = $this->stats;
        }

        return $payload;
    }
}
