<?php

use GuzzleHttp\Client as MockClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Teemill\ImageApi\Client as ApiClient;

function createMockClient(array $mock_responses = [], array &$history = []): ApiClient
{
    $stack = HandlerStack::create(new MockHandler($mock_responses));

    $stack->push(Middleware::history($history));

    return new ApiClient(
        new MockClient([
            'handler' => $stack,
        ]),
        'this-is-a-test-secret-that-is-long-enough-for-hs256-validation!'
    );
}
