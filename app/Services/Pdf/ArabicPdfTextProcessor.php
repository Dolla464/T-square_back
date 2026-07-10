<?php

namespace App\Services\Pdf;

use App\Support\I18N_Arabic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ArabicPdfTextProcessor
{
    private const ARABIC_PATTERN = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    private const SKIP_KEYS = [
        'logoData',
        'content',
        'mime',
        'filename',
    ];

    public function __construct(
        private I18N_Arabic $arabic
    ) {}

    public function process(mixed $data): mixed
    {
        return $this->processValue($data);
    }

    private function processValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->shouldSkipKey($key)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->processString($value);
        }

        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item, int|string $itemKey) => $this->processValue(
                $item,
                is_string($itemKey) ? $itemKey : null
            ));
        }

        if (is_array($value)) {
            $processed = [];

            foreach ($value as $arrayKey => $item) {
                $processed[$arrayKey] = $this->processValue(
                    $item,
                    is_string($arrayKey) ? $arrayKey : null
                );
            }

            return $processed;
        }

        if (is_object($value)) {
            return $this->processObject($value);
        }

        return $value;
    }

    private function processObject(object $value): object
    {
        if ($value instanceof Model) {
            $clone = clone $value;
            $attributes = $clone->getAttributes();

            foreach ($attributes as $attribute => $attributeValue) {
                if (is_string($attributeValue)) {
                    $clone->setAttribute($attribute, $this->processString($attributeValue));
                }
            }

            return $clone;
        }

        $clone = clone $value;

        foreach (get_object_vars($clone) as $property => $propertyValue) {
            $clone->{$property} = $this->processValue(
                $propertyValue,
                is_string($property) ? $property : null
            );
        }

        return $clone;
    }

    private function processString(string $value): string
    {
        if (! $this->containsArabic($value)) {
            return $value;
        }

        if ($this->looksLikeEncodedPayload($value)) {
            return $value;
        }

        return $this->arabic->utf8Glyphs($value, max_chars: 200, hindo: false);
    }

    private function containsArabic(string $value): bool
    {
        return (bool) preg_match(self::ARABIC_PATTERN, $value);
    }

    private function shouldSkipKey(string $key): bool
    {
        return in_array($key, self::SKIP_KEYS, true);
    }

    private function looksLikeEncodedPayload(string $value): bool
    {
        if (str_starts_with($value, 'data:')) {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value) === 1 && strlen($value) > 120) {
            return true;
        }

        return false;
    }
}
