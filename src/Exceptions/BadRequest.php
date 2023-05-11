<?php

namespace Cangokdayi\WPFacades\Exceptions;

final class BadRequest extends CustomException
{
    protected function errorMessage(): string
    {
        return "Bad request";
    }
}