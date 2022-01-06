<?php

namespace Teemill\ImageApi;

use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class Client
{
    protected const algorithm = 'HS256';
    protected const compatible_mimes = [
        'image/webp',
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    protected ClientInterface $client;
    protected string $secret;
    protected string $token;

    public function __construct(
        ClientInterface $client,
        string          $secret
    ) {
        $this->secret = $secret;
        $this->client = $client;

        $this->generateAuthenticationToken();
    }

    /**
     * @throws GuzzleException
     */
    public function upload(string $filename, $data): array
    {
        return $this->sendClientRequest(
            'POST',
            'upload',
            [
                'multipart' => [
                    [
                        'name' => 'file',
                        'filename' => $filename,
                        'contents' => $data,
                    ],
                ],
            ]
        );
    }

    public static function isCompatibleMime(string $mime): bool
    {
        return in_array($mime, static::compatible_mimes);
    }

    protected function generateAuthenticationToken(): void
    {
        $timestamp = time();

        $this->token = JWT::encode(
            [
                'nbf' => $timestamp,
                'iat' => $timestamp,
                'exp' => strtotime('+1 day', $timestamp),
            ],
            $this->secret,
            static::algorithm
        );
    }

    protected function sendClientRequest(
        string $method,
        string $resource,
        array  $data
    ): array {
        $response = $this->client->request(
            $method,
            $resource,
            array_merge_recursive([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer $this->token",
                ],
            ], $data)
        );

        return json_decode(
            $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
