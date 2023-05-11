<?php

namespace Cangokdayi\WPFacades\Exceptions;

final class Unauthorized extends CustomException
{
    protected function errorMessage(): string
    {
        return 'Server failed to authenticate the request';
    }

    protected function statusCode(): int
    {
        return 401;
    }
}   
