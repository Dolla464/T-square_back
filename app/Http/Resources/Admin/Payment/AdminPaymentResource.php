<?php

namespace App\Http\Resources\Admin\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fields = $request->routeIs('*show*') 
                ? PaymentFieldList::fieldsForDetail() 
                : PaymentFieldList::fieldsForList();

        $data = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $this->resource->getAttributes())) {
                $value = $this->{$field};
                $data[$field] = $value;
            }
        }

        return $data;
    }
}
