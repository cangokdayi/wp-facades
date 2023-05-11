<?php

namespace Cangokdayi\WPFacades\Exceptions;

final class InternalServerError extends CustomException
{
    protected function errorMessage(): string
    {
        return 'Something went wrong while processing your request';
    }
    
    protected function statusCode(): int
    {
        return 500;
    }
}
