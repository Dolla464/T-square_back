<?php

namespace App\Http\Resources\Admin\Certificate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AdminCertificateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Field set is chosen by route name so index and show return different shapes.
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
     * Walk the dot-notation field list and build the output array.
     * Handles plain scalar fields, nested model paths, and Collection paths
     * (e.g. "enrollments.is_completed", "enrollments.learningGroup.group_name").
     *
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function filterFields(array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            if (! str_contains($field, '.')) {
                // Scalar attribute — cast enums to their string value automatically.
                $raw = $this->getAttributeValue($field);
                $out[$field] = $raw instanceof \BackedEnum ? $raw->value : $raw;
                continue;
            }

            $segments = explode('.', $field);
            $root     = array_shift($segments);

            if (! $root) {
                continue;
            }

            $value = $this->resource->{$root} ?? null;

            foreach ($segments as $seg) {
                if ($value === null) {
                    break;
                }

                if ($value instanceof Collection) {
                    // One level deeper from a collection: gather and stop.
                    $value = $value->map(function ($item) use ($seg, &$segments) {
                        return $item instanceof \Illuminate\Database\Eloquent\Model
                            ? $item->{$seg} ?? null
                            : (is_array($item) ? ($item[$seg] ?? null) : null);
                    })->all();
                    break;
                }

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
