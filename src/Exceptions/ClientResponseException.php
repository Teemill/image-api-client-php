<?php

namespace Teemill\ImageApi\Exceptions;

use JsonException;
use RuntimeException;

class ClientResponseException extends RuntimeException
{
    public static function invalidJson(JsonException $previous): ClientResponseException
    {
        return new static("Invalid JSON detected in API response", 0, $previous);
    }
}
