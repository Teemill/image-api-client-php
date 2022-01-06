<?php

use GuzzleHttp\Client as MockClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Teemill\ImageApi\Client as ApiClient;

function createMockClient(array $mock_responses = []): ApiClient
{
    return new ApiClient(
        new MockClient([
            'handler' => HandlerStack::create(new MockHandler($mock_responses)),
        ]),
        'secret'
    );
}
