<?php

use GuzzleHttp\Psr7\Response;
use Teemill\ImageApi\Client as ApiClient;

uses()->group('client');

it('can detect compatible mimes', function (string $mime, bool $valid) {
    expect(ApiClient::isCompatibleMime($mime))->toEqual($valid);
})->with([
    ['image/png', true],
    ['image/jpeg', true],
    ['image/jpg', true],
    ['image/webp', true],
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
