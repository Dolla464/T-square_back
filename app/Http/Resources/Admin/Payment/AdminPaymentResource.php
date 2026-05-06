<?php

namespace App\Http\Resources\Admin\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

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
            // special-case: hasMany like enrollments.course.title -> return list of values
            if (Str::startsWith($field, 'enrollments.')) {
                $pathInsideEnrollment = Str::after($field, 'enrollments.');

                $data[$field] = $this->whenLoaded('enrollments', function () use ($pathInsideEnrollment) {
                    return $this->enrollments
                        ->map(fn ($enrollment) => data_get($enrollment, $pathInsideEnrollment))
                        ->filter(fn ($value) => $value !== null && $value !== '')
                        ->values()
                        ->all();
                }, []);

                continue;
            }

            // dot-notation fields like student.full_name, student.user.email
            $data[$field] = data_get($this->resource, $field);
        }

        return $data;
    }
}
