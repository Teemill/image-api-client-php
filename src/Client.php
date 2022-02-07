<?php

namespace Teemill\ImageApi;

use Firebase\JWT\ExpiredException;
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
    protected const algorithm = 'HS256';
    protected const compatible_mimes = [
        'image/webp',
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    protected ClientInterface $client;
    protected string $secret;
    public string $token;

    public function __construct(
        ClientInterface $client,
        string          $secret,
        ?int            $token_expiration = null
    ) {
        $this->secret = $secret;
        $this->client = $client;

        $this->generateAuthenticationToken($token_expiration);
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
        $response = $this->sendClientRequest(
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
        return in_array($mime, static::compatible_mimes);
    }

    public function generateAuthenticationToken(?int $expiration = null): void
    {
        $timestamp = time();

        $this->token = JWT::encode(
            array_merge(
                [
                    'nbf' => $timestamp,
                    'iat' => $timestamp,
                ],
                $expiration ? ['exp' => $expiration] : []
            ),
            $this->secret,
            static::algorithm
        );
    }

    /**
     * @throws GuzzleException
     * @throws AuthenticationException
     */
    protected function sendClientRequest(
        string $method,
        string $resource,
        array  $data = []
    ): ResponseInterface {
        try {
            JWT::decode($this->token, $this->secret, [static::algorithm]);
        } catch (ExpiredException $exception) {
            throw AuthenticationException::tokenExpired();
        }

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

    protected function format(ResponseInterface $response): array
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
