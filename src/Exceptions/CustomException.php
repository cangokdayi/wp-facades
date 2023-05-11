<?php

namespace Cangokdayi\WPFacades\Exceptions;

abstract class CustomException extends \Exception
{
    /**
     * Returns the error message
     */
    abstract protected function errorMessage(): string;

    public function __construct(
        $message = null,
        $code = 0,
        \Throwable $previous = null
    ) {
        $this->code = $code ?: $this->statusCode();

        parent::__construct(
            $message ?? $this->errorMessage(),
            $this->code,
            $previous
        );
    }

    /**
     * Returns the HTTP Status code for API requests - defaults to 400
     */
    protected function statusCode(): int
    {
        return 400;
    }

    /**
     * Returns the shortened class name of the exception to use as the error
     * type on API responses.
     * 
     * You can override this method to use a custom error type as you require.
     */
    public function getType(): string
    {
        return (new \ReflectionClass(static::class))->getShortName();
    }
}
