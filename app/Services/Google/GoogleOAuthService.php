<?php

namespace App\Services\Google;

use Google\Client;
use Google\Service\Drive;

class GoogleOAuthService
{
    private const DRIVE_READONLY_SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    private const STATE_TTL_SECONDS = 900;

    public function makeClient(?string $accessToken = null): Client
    {
        $client = new Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([
            self::DRIVE_READONLY_SCOPE,
            'openid',
            'email',
        ]);

        if ($accessToken) {
            $client->setAccessToken($accessToken);
        }

        return $client;
    }

    public function buildAuthUrl(int $accountId, int $adminUserId): string
    {
        $client = $this->makeClient();
        $state = $this->encodeState($accountId, $adminUserId);
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function exchangeCode(string $code): array
    {
        $client = $this->makeClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException($token['error_description'] ?? $token['error']);
        }

        return $token;
    }

    public function refreshToken(string $refreshToken): array
    {
        $client = $this->makeClient();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($token['error'])) {
            throw new \RuntimeException($token['error_description'] ?? $token['error']);
        }

        return $token;
    }

    public function makeDriveService(Client $client): Drive
    {
        return new Drive($client);
    }

    public function encodeState(int $accountId, int $adminUserId): string
    {
        return encrypt(json_encode([
            'account_id' => $accountId,
            'admin_user_id' => $adminUserId,
            'issued_at' => now()->timestamp,
        ]));
    }

    public function decodeState(string $state): array
    {
        $payload = json_decode(decrypt($state), true);

        if (! is_array($payload) || empty($payload['account_id']) || empty($payload['admin_user_id'])) {
            throw new \InvalidArgumentException('Invalid OAuth state.');
        }

        if (
            empty($payload['issued_at'])
            || now()->timestamp - (int) $payload['issued_at'] > self::STATE_TTL_SECONDS
        ) {
            throw new \InvalidArgumentException('OAuth state expired.');
        }

        return $payload;
    }
}
