<?php

namespace Teemill\ImageApi;

use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\StreamWrapper;
use JsonException;
use Psr\Http\Message\ResponseInterface;

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

    public function exists(string $filename): bool
    {
        try {
            return $this->sendClientRequest('HEAD', $filename)->getStatusCode() === 200;
        } catch (ClientException $exception) {
            return false;
        }
    }

    /**
     * @throws GuzzleException
     */
    public function upload(string $filename, $data): array
    {
        $response = $this->sendClientRequest(
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

        return $this->format($response);
    }

    /**
     * @throws GuzzleException
     */
    public function healthz(): array
    {
        $response = $this->sendClientRequest('GET', 'healthz');

        return $this->format($response);
    }

    public function download(string $path)
    {
        $response = $this->sendClientRequest('GET', $path);

        return StreamWrapper::getResource($response->getBody());
    }

    public function metadata(string $path): array
    {
        $response = $this->sendClientRequest('HEAD', $path);

        return [
            'size' => $response->getHeader('content-size')[0],
            'mimetype' => $response->getHeader('content-type')[0],
            'timestamp' => time(),
            'path' => "/$path",
        ];
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
        array  $data = []
    ): ResponseInterface {
        return $this->client->request(
            $method,
            $resource,
            array_merge_recursive([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer $this->token",
                ],
            ], $data)
        );
    }

    /**
     * @throws JsonException
     */
    protected function format(ResponseInterface $response): array
    {
        return json_decode(
            $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
