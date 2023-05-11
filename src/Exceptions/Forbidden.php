<?php

namespace Cangokdayi\WPFacades\Exceptions;

final class Forbidden extends CustomException
{
    protected function errorMessage(): string
    {
        return 'You\'re not allowed to access this resource';
    }

    protected function statusCode(): int
    {
        return 403;
    }
}