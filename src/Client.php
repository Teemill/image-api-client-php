<?php

namespace Teemill\ImageApi;

use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\StreamWrapper;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Teemill\ImageApi\Exceptions\ClientResponseException;

class Client
{
    protected const AUTHENTICATION_ALGORITHM = 'HS256';
    protected const COMPATIBLE_MIMES = ['image/webp', 'image/jpeg', 'image/jpg', 'image/png'];

    protected ClientInterface $client;
    protected string $secret;
    protected array $default_client_headers = [
        'Accept' => 'application/json',
    ];

    public function __construct(
        ClientInterface $client,
        string          $secret
    ) {
        $this->secret = $secret;
        $this->client = $client;
    }

    /**
     * @throws GuzzleException
     */
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
        $response = $this->sendAuthenticatedClientRequest(
            'POST',
            'store',
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

        return $this->formatResponse($response);
    }

    /**
     * @throws GuzzleException
     */
    public function healthz(): array
    {
        $response = $this->sendClientRequest('GET', 'healthz');

        return $this->formatResponse($response);
    }

    /**
     * @throws GuzzleException
     */
    public function download(string $path)
    {
        $response = $this->sendClientRequest('GET', $path);

        return StreamWrapper::getResource($response->getBody());
    }

    /**
     * @throws GuzzleException
     */
    public function metadata(string $path): array
    {
        $response = $this->sendClientRequest('HEAD', $path);

        return [
            'size' => $response->getHeader('content-length')[0],
            'mimetype' => $response->getHeader('content-type')[0],
            'timestamp' => time(),
            'path' => "/$path",
        ];
    }

    public static function isCompatibleMime(string $mime): bool
    {
        return in_array($mime, static::COMPATIBLE_MIMES);
    }

    /**
     * @throws GuzzleException
     */
    protected function sendClientRequest(
        string $method,
        string $resource,
        array  $data = []
    ): ResponseInterface {
        return $this->client->request(
            $method,
            $resource,
            array_merge_recursive([
                'headers' => $this->default_client_headers,
            ], $data)
        );
    }

    /**
     * @throws GuzzleException
     */
    protected function sendAuthenticatedClientRequest(
        string $method,
        string $resource,
        array  $data = []
    ) {
        return $this->client->request(
            $method,
            $resource,
            array_merge_recursive([
                'headers' => array_merge($this->default_client_headers, [
                    'Authorization' => "Bearer {$this->generateAuthenticationToken()}",
                ]),
            ], $data)
        );
    }

    protected function generateAuthenticationToken(): string
    {
        $timestamp = time();

        return JWT::encode(
            [
                'nbf' => $timestamp,
                'iat' => $timestamp,
                'exp' => strtotime('+15 minutes'),
            ],
            $this->secret,
            static::AUTHENTICATION_ALGORITHM
        );
    }

    protected function formatResponse(ResponseInterface $response): array
    {
        try {
            return json_decode(
                $response->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw ClientResponseException::invalidJson($exception);
        }
    }
}
