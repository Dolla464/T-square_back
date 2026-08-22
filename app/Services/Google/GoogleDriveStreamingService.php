<?php

namespace App\Services\Google;

use App\Exceptions\GoogleAccountDisconnectedException;
use App\Exceptions\GoogleDriveFileAccessException;
use App\Models\GoogleStorageAccount;
use App\Services\Google\GoogleStorageAccountService;
use Google\Service\Drive;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleDriveStreamingService
{
    private const CHUNK_SIZE = 1024 * 1024;

    public function __construct(
        private readonly GoogleStorageAccountService $accountService,
    ) {}

    /**
     * @return array{metadata: array<string, mixed>, stream: StreamInterface, status: int, headers: array<string, string>}
     */
    public function openStream(GoogleStorageAccount $account, string $fileId, ?string $rangeHeader): array
    {
        $account = $this->accountService->refreshAccessTokenIfNeeded($account);
        $metadata = $this->getFileMetadata($account, $fileId);
        $drive = $this->accountService->getDriveServiceForAccount($account);

        $httpClient = $drive->getClient()->authorize();
        $url = sprintf(
            'https://www.googleapis.com/drive/v3/files/%s?alt=media&supportsAllDrives=true',
            urlencode($fileId)
        );

        $requestHeaders = [
            'Authorization' => 'Bearer '.$account->access_token,
        ];

        if ($rangeHeader) {
            $requestHeaders['Range'] = $rangeHeader;
        }

        $response = $httpClient->request('GET', $url, [
            'headers' => $requestHeaders,
            'stream' => true,
            'http_errors' => false,
        ]);

        $status = $response->getStatusCode();

        if ($status === 401 || $status === 403) {
            throw new GoogleDriveFileAccessException('Unable to stream file from Google Drive.');
        }

        if ($status >= 400) {
            throw new GoogleDriveFileAccessException('Google Drive streaming request failed.');
        }

        return [
            'metadata' => $metadata,
            'stream' => $response->getBody(),
            'status' => $status,
            'headers' => $this->normalizeResponseHeaders($response->getHeaders(), $metadata),
        ];
    }

    public function streamResponse(array $openedStream): StreamedResponse
    {
        $stream = $openedStream['stream'];
        $status = $openedStream['status'];
        $headers = $openedStream['headers'];

        return response()->stream(function () use ($stream) {
            while (! $stream->eof()) {
                echo $stream->read(self::CHUNK_SIZE);

                if (connection_aborted()) {
                    break;
                }

                flush();
            }

            $stream->close();
        }, $status, $headers);
    }

    /**
     * @return array{id: string, mimeType: string, size: int}
     */
    public function getFileMetadata(GoogleStorageAccount $account, string $fileId): array
    {
        $cacheKey = "google_drive_file_meta:{$account->id}:{$fileId}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($account, $fileId) {
            $drive = $this->accountService->getDriveServiceForAccount($account);

            $file = $drive->files->get($fileId, [
                'fields' => 'id,mimeType,size,trashed',
                'supportsAllDrives' => true,
            ]);

            if ($file->getTrashed()) {
                throw new GoogleDriveFileAccessException('File is in trash.');
            }

            return [
                'id' => $file->getId(),
                'mimeType' => $file->getMimeType() ?: 'video/mp4',
                'size' => (int) ($file->getSize() ?? 0),
            ];
        });
    }

    /**
     * @param  array<string, array<int, string>>  $rawHeaders
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>
     */
    private function normalizeResponseHeaders(array $rawHeaders, array $metadata): array
    {
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => $metadata['mimeType'] ?? 'video/mp4',
            'Content-Disposition' => 'inline',
            'X-Download-Options' => 'noopen',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ];

        foreach (['Content-Length', 'Content-Range'] as $header) {
            if (! empty($rawHeaders[$header][0])) {
                $headers[$header] = $rawHeaders[$header][0];
            }
        }

        if (empty($headers['Content-Length']) && ! empty($metadata['size'])) {
            $headers['Content-Length'] = (string) $metadata['size'];
        }

        return $headers;
    }
}
