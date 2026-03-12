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
        'a]3Fz!Qr@8kL9mNp#2xYw$5vBcD7eGh'
    );
}
