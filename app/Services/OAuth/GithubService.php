<?php

namespace App\Services\OAuth;

use App\Contracts\OAuthServiceContract;
use GuzzleHttp\Client;

class GithubService implements OAuthServiceContract
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.github.com',
            'timeout' => 5.0
        ]);
    }

    public function auth(string $code): array
    {
        $url = "https://github.com/login/oauth/access_token";

        $response = $this->client->request('POST', $url, [
            'form_params' => [
                'client_id' => env('GITHUB_OAUTH_ID'),
                'client_secret' => env('GITHUB_OAUTH_SECRET'),
                'code' => $code,
            ],
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);
        return json_decode($response->getBody(), true);
    }

    public function getUser(string $token): array
    {
        $uri = "/user";

        $response = $this->client->request('GET', $uri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        return json_decode($response->getBody(), true);
    }
}
