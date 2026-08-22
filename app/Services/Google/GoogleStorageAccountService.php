<?php

namespace App\Services\Google;

use App\Exceptions\GoogleAccountDisconnectedException;
use App\Exceptions\GoogleDriveFileAccessException;
use App\Models\GoogleStorageAccount;
use App\Services\Video\VideoActivityLogger;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;

class GoogleStorageAccountService
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
        private readonly VideoActivityLogger $videoLogger,
    ) {}

    public function refreshAccessTokenIfNeeded(GoogleStorageAccount $account): GoogleStorageAccount
    {
        if (! $account->refresh_token) {
            $this->markDisconnected($account, 'Missing refresh token.');

            throw new GoogleAccountDisconnectedException;
        }

        $needsRefresh = ! $account->access_token
            || ! $account->token_expires_at
            || $account->token_expires_at->lte(now()->addMinutes(5));

        if (! $needsRefresh) {
            return $account;
        }

        try {
            $token = $this->oauthService->refreshToken($account->refresh_token);

            $account->update([
                'access_token' => $token['access_token'] ?? null,
                'token_expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds((int) $token['expires_in'])
                    : now()->addHour(),
                'scope' => $token['scope'] ?? $account->scope,
                'status' => GoogleStorageAccount::STATUS_CONNECTED,
                'last_error' => null,
            ]);

            $this->videoLogger->log('google_token_refresh', [
                'storage_account_id' => $account->id,
            ]);

            return $account->fresh();
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->markDisconnected($account, $message);

            $this->videoLogger->log('google_account_error', [
                'storage_account_id' => $account->id,
                'error' => $message,
            ]);

            throw new GoogleAccountDisconnectedException($message);
        }
    }

    public function testConnection(GoogleStorageAccount $account): GoogleStorageAccount
    {
        try {
            $account = $this->refreshAccessTokenIfNeeded($account);
            $client = $this->oauthService->makeClient($account->access_token);
            $drive = $this->oauthService->makeDriveService($client);
            $about = $drive->about->get(['fields' => 'user,storageQuota']);

            $account->update([
                'email' => $about->getUser()?->getEmailAddress() ?? $account->email,
                'status' => GoogleStorageAccount::STATUS_CONNECTED,
                'last_checked_at' => now(),
                'last_error' => null,
            ]);

            return $account->fresh();
        } catch (\Throwable $e) {
            $this->markDisconnected($account, $e->getMessage());

            throw $e;
        }
    }

    public function disconnect(GoogleStorageAccount $account): GoogleStorageAccount
    {
        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => GoogleStorageAccount::STATUS_DISCONNECTED,
            'last_error' => null,
        ]);

        return $account->fresh();
    }

    public function getDriveServiceForAccount(GoogleStorageAccount $account): Drive
    {
        $account = $this->refreshAccessTokenIfNeeded($account);
        $client = $this->oauthService->makeClient($account->access_token);

        return $this->oauthService->makeDriveService($client);
    }

    public function validateFileAccess(GoogleStorageAccount $account, string $fileId): array
    {
        try {
            $drive = $this->getDriveServiceForAccount($account);
            $file = $drive->files->get($fileId, [
                'fields' => 'id,name,mimeType,size,trashed',
                'supportsAllDrives' => true,
            ]);

            if ($file->getTrashed()) {
                throw new GoogleDriveFileAccessException('File is in trash.');
            }

            return [
                'status' => 'valid',
                'message' => 'File is accessible.',
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        } catch (GoogleAccountDisconnectedException $e) {
            return [
                'status' => 'account_disconnected',
                'message' => 'Google account is disconnected.',
            ];
        } catch (\Throwable $e) {
            Log::warning('google_drive_file_validation_failed', [
                'storage_account_id' => $account->id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'inaccessible',
                'message' => 'Unable to verify file access with the connected Google account.',
            ];
        }
    }

    private function markDisconnected(GoogleStorageAccount $account, string $message): void
    {
        $account->update([
            'status' => GoogleStorageAccount::STATUS_DISCONNECTED,
            'last_error' => $message,
            'last_checked_at' => now(),
        ]);
    }
}
