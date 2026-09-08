<?php

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Teemill\ImageApi\Client as ApiClient;
use Teemill\ImageApi\Exceptions\ClientResponseException;

uses()->group('client');

it('can detect compatible mimes', function (string $mime, bool $valid) {
    expect(ApiClient::isCompatibleMime($mime))->toEqual($valid);
})->with([
    ['image/png', true],
    ['image/jpeg', true],
    ['image/jpg', true],
    ['image/webp', true],
    ['image/gif', true],
    ['image/pdf', false],
]);

it('can be instantiated with credentials', function () {
    $client = createMockClient();

    expect($client)->toBeInstanceOf(ApiClient::class);
});

it('can upload an image to a specified directory', function () {
    $client = createMockClient([
        new Response(200, [], json_encode([
            'id' => 'example.jpg',
            'resource' => 'https://images.localhost:8080/example.jpg',
        ], JSON_THROW_ON_ERROR)),
    ]);

    $response = $client->upload('example.jpg', 'example');

    expect($response)
        ->toBeArray()
        ->id->toEqual('example.jpg')
        ->resource->toEqual('https://images.localhost:8080/example.jpg');
});


it('can delete a file', function () {
    $history = [];

    $client = createMockClient([
        new Response(204),
    ], $history);

    $client->delete('example.jpg');

    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];

    expect($request->getMethod())->toEqual('DELETE');
    expect((string) $request->getUri())->toEqual('example.jpg');
    expect($request->getHeaderLine('Authorization'))->toStartWith('Bearer ');
});

it('surfaces a failed delete so the caller can retry', function () {
    $client = createMockClient([
        new Response(500),
    ]);

    $client->delete('example.jpg');
})->throws(ServerException::class);

it('can perform a health check', function () {
    $client = createMockClient([
        new Response(200, [], json_encode([
            'status' => 'UP',
            'version' => '0.0.1',
            'cache' => 'connected',
        ], JSON_THROW_ON_ERROR)),
    ]);

    $response = $client->healthz();

    expect($response)
        ->toBeArray()
        ->status->toEqual('UP')
        ->version->toEqual('0.0.1')
        ->cache->toEqual('connected');
});

it('can check if a file exists', function () {
    $client = createMockClient([
        new Response(200),
        new Response(404),
    ]);

    expect($client->exists('should-exist.jpg'))->toBeTrue();
    expect($client->exists('should-not-exist.jpg'))->toBeFalse();
});

it('can fetch the metadata for a file', function () {
    $client = createMockClient([
        new Response(202, [
            'content-length' => 5000,
            'content-type' => 'image/png',
        ]),
    ]);

    expect($client->metadata('metadata.jpg'))
        ->toBeArray()
        ->toHaveKeys([
            'size', 'mimetype',
        ]);
});

it('can download a file', function () {
    $client = createMockClient([
        new Response(200, [
            'content-length' => 5000,
            'content-type' => 'image/png',
        ], file_get_contents(__DIR__.'/fixtures/mock.jpeg')),
    ]);

    $response = $client->download('download.jpg');

    $contents = stream_get_contents($response);

    fclose($response);
    unset($response);

    $file_info = new finfo(FILEINFO_MIME_TYPE);

    expect($file_info->buffer($contents))->toEqual('image/jpeg');
});

it('throws an exception when invalid JSON is received', function () {
    $client = createMockClient([
        new Response(200, [], '{invalid json}'),
    ]);

    $this->expectException(ClientResponseException::class);

    $response = $client->healthz();
});
