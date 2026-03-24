<?php

namespace Weave\Google;

use Google\Client;
use InvalidArgumentException;
use Weave\Credentials\CredentialManager;

class GoogleClient
{
    public static function make(array $scopes): Client
    {
        $credentials = app(CredentialManager::class);

        $client = new Client();
        $client->setScopes($scopes);

        $keyJson = $credentials->get('GOOGLE_SERVICE_ACCOUNT_JSON');

        if (is_string($keyJson) && $keyJson !== '') {
            $decoded = json_decode($keyJson, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('GOOGLE_SERVICE_ACCOUNT_JSON must be valid JSON.');
            }
            $client->setAuthConfig($decoded);

            return $client;
        }

        $accessToken = $credentials->get('GOOGLE_ACCESS_TOKEN');
        if (! is_string($accessToken) || $accessToken === '') {
            throw new InvalidArgumentException('Configure GOOGLE_SERVICE_ACCOUNT_JSON or OAuth tokens (GOOGLE_ACCESS_TOKEN, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REFRESH_TOKEN).');
        }

        $client->setAccessToken([
            'access_token' => $accessToken,
            'refresh_token' => $credentials->get('GOOGLE_REFRESH_TOKEN'),
            'expires_in' => 3600,
        ]);

        $client->setClientId($credentials->get('GOOGLE_CLIENT_ID'));
        $client->setClientSecret($credentials->get('GOOGLE_CLIENT_SECRET'));

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken();
        }

        return $client;
    }
}
