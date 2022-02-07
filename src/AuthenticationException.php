<?php

namespace Teemill\ImageApi;

use RuntimeException;

class AuthenticationException extends RuntimeException
{
    public static function tokenExpired(): AuthenticationException
    {
        return new static('Authentication token expired.');
    }
}
