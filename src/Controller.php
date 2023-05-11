<?php

namespace Cangokdayi\WPFacades;

use Cangokdayi\WPFacades\Exceptions\CustomException;
use Cangokdayi\WPFacades\Exceptions\Forbidden;
use Cangokdayi\WPFacades\Exceptions\Unauthorized;
use Cangokdayi\WPFacades\Traits\HandlesRequests;
use ReflectionMethod;
use WP_Error;
use WP_REST_Response;

/**
 * Base controller class. This class will intercept all the method calls
 * of its fish classes in order to catch & process exceptions at the top
 * level.
 * 
 * It has basic dependency injection and error handling/masking features.
 * 
 * All of the route callback methods of the fish classes must be declared
 * as "protected" methods otherwise the base controller class won't be able 
 * to intercept them when they're called.
 * 
 * You can throw errors from your callback or auth callback methods, they'll be
 * catched & converted to WP_REST_Response or WP_Error objects at the top-level
 * here inside the getErrorResponse() method.
 * 
 * @todo Assert the method accessibility in `Route::initCallback()` to avoid
 *       registering routes with public controller methods.
 */
abstract class Controller
{
    use HandlesRequests;

    public function __construct()
    {
        add_filter(
            'rest_request_after_callbacks',
            [$this, 'maskWordpressError']
        );
    }

    public function __call($name, $arguments)
    {
        try {
            return call_user_func_array(
                [(new static()), $name],
                $this->resolveDependencies($name, $arguments)
            );
        } catch (\Throwable $e) {
            return $this->getErrorResponse($e);
        }
    }

    public static function __callStatic($name, $arguments)
    {
        try {
            return call_user_func_array(
                [static::class, $name],
                (new static())->resolveDependencies($name, $arguments)
            );
        } catch (\Throwable $e) {
            return (new static())->getErrorResponse($e);
        }
    }

    protected function getErrorResponse(\Throwable $exception): object
    {
        $isAuthError = is_a($exception, Unauthorized::class)
            || is_a($exception, Forbidden::class)
            || in_array($exception->getCode(), [401, [403]]);

        $errorCode = $exception instanceof CustomException
            ? $exception->getType()
            : 'InternalServerError';

        // WP permission_callback methods can only return boolean 
        // or WP_Error objects thus we need to modify the behavior
        if ($isAuthError) {
            return new WP_Error(
                $errorCode,
                $exception->getMessage(),
                ['status' => $this->getStatusCode($exception)]
            );
        }

        return new WP_REST_Response(
            $this->errorTemplate(
                $errorCode,
                $exception->getMessage()
            ),
            $this->getStatusCode($exception)
        );
    }

    /**
     * Masks the WP_Error responses returned from auth controllers to modify the
     * final JSON response before returning it.
     * 
     * @param WP_REST_Response|WP_Error $response
     * @see https://developer.wordpress.org/reference/hooks/rest_request_after_callbacks/
     */
    public function maskWordPressError(object $response): WP_REST_Response
    {
        if ($response instanceof WP_REST_Response) {
            return $response;
        }

        return new WP_REST_Response(
            $this->errorTemplate(
                $response->get_error_code(),
                $response->get_error_message()
            ),
            $response->get_error_data()['status'] ?? 401
        );
    }

    /**
     * Resolves the class dependencies for the given controller method's params
     * before the method is called
     * 
     * Keep in mind that the given dependencies must be initializable without
     * passing any params to the constructor otherwise an error will be thrown.
     * 
     * @param array $passedArgs Params passed w/ the method call
     * @throws \ArgumentCountError If the given dependency expects constructor
     *                             parameters.
     */
    public function resolveDependencies(
        string $methodName,
        array $passedArgs = []
    ): array {
        $method = new ReflectionMethod(new static(), $methodName);
        $params = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType()->getName();
            $position = $param->getPosition();

            // for WP_REST Request/Response objects passed from WP Core
            foreach ($passedArgs as $argument) {
                if ($argument instanceof $type) {
                    $params[$position] = $argument;

                    continue 2;
                }
            }

            $params[$position] = new $type();
        }

        return $params;
    }

    /**
     * Compares the given exception's code to valid HTTP Status Codes range and
     * returns a status code based on that.
     */
    private function getStatusCode(\Throwable $exception): int
    {
        $code = $exception->getCode();

        return $code >= 100 && 599 >= $code
            ? $code
            : 500;
    }

    /**
     * Returns a WP_REST_Response object with the given arguments
     * 
     * @param mixed $body Response body, arrays will be casted to JSON.
     * @param int $status HTTP Status code
     * @param array $headers Additional response headers
     */
    protected function response(
        $body = null,
        int $status = 200,
        array $headers = []
    ): WP_REST_Response {
        return new WP_REST_Response($body, $status, $headers);
    }
}
