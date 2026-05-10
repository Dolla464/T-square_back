<?php

namespace App\Http\Resources\Admin\Certificate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class AdminCertificateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fields = $request->routeIs('*show*')
            ? CertificateFieldList::fieldsForDetail()
            : CertificateFieldList::fieldsForList();

        return $this->filterFields($fields);
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, mixed>
     */
    private function filterFields(array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            if (! str_contains($field, '.')) {
                $out[$field] = $this->getAttributeValue($field);
                continue;
            }

            $segments = explode('.', $field);
            $root = array_shift($segments);
            if (! $root) {
                continue;
            }

            $value = $this->resource;
            foreach (array_merge([$root], $segments) as $seg) {
                if ($value === null) {
                    break;
                }

                // Collection handling: allow "enrollments.is_completed" to return array of values.
                if ($value instanceof \Illuminate\Support\Collection) {
                    $value = $value->pluck($seg)->all();
                    break;
                }

                // Model/resource/array access.
                if (is_array($value)) {
                    $value = Arr::get($value, $seg);
                } else {
                    $value = $value->{$seg} ?? null;
                }
            }

            Arr::set($out, $field, $value);
        }

        return $out;
    }
}
