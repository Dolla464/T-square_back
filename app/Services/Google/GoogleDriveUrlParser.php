<?php

namespace App\Services\Google;

use InvalidArgumentException;

class GoogleDriveUrlParser
{
    private const ALLOWED_HOSTS = [
        'drive.google.com',
        'docs.google.com',
    ];

    /**
     * @return array{file_id: string, host: string}
     */
    public function parse(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            throw new InvalidArgumentException('Google Drive URL is required.');
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw new InvalidArgumentException('Invalid Google Drive URL.');
        }

        $host = strtolower($parts['host']);

        if (! in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new InvalidArgumentException('Only Google Drive URLs are allowed.');
        }

        $path = $parts['path'] ?? '';
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $path, $matches)) {
            return ['file_id' => $matches[1], 'host' => $host];
        }

        if (preg_match('#/open#', $path) && ! empty($query['id'])) {
            return ['file_id' => $query['id'], 'host' => $host];
        }

        if (preg_match('#/uc#', $path) && ! empty($query['id'])) {
            return ['file_id' => $query['id'], 'host' => $host];
        }

        throw new InvalidArgumentException('Unable to extract Google Drive file ID from URL.');
    }

    public function isValid(string $url): bool
    {
        try {
            $this->parse($url);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
