<?php

namespace App\Services\ActivityLog;

use Illuminate\Http\UploadedFile;

class RequestDataSanitizer
{
    private const REDACTED = '[REDACTED]';

    private const UPLOADED_FILE = '[uploaded file]';

    private const TRUNCATED = '[truncated]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'current_password',
        'password_confirmation',
        'token',
        '_token',
        'access_token',
        'refresh_token',
        'authorization',
        'client_secret',
        'secret',
    ];

    public function __construct(
        private int $maxJsonBytes = 8192,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function sanitize(array $data): ?array
    {
        if ($data === []) {
            return null;
        }

        $sanitized = $this->sanitizeValue($data);

        if (! is_array($sanitized)) {
            return null;
        }

        return $this->truncateIfNeeded($sanitized);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return self::UPLOADED_FILE;
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $result[$key] = self::REDACTED;

                    continue;
                }

                $result[$key] = $this->sanitizeValue($item);
            }

            return $result;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function truncateIfNeeded(array $data): array
    {
        $encoded = json_encode($data);

        if ($encoded === false || strlen($encoded) <= $this->maxJsonBytes) {
            return $data;
        }

        return [
            '_note' => self::TRUNCATED,
            '_preview' => substr($encoded, 0, $this->maxJsonBytes),
        ];
    }
}
