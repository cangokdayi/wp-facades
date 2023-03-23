<?php

namespace Cangokdayi\WPFacades;

use ReflectionMethod as Method;

/**
 * Provides an easy-to-use interface to register WP Rest API routes
 */
final class Route
{
    /**
     * Default namespace to register the routes
     */
    private string $defaultNamespace = 'rest';

    /**
     * Registers a GET route
     * 
     * @param callable|array $callback Controller callback
     * @param callable|array $auth Auth controller 
     * @param null|string $namespace Defaults to "rest"
     */
    public static function get(
        string $route,
        $callback,
        $auth,
        ?string $namespace = null
    ): void {
        (new self())->initRoute($route, 'GET', $callback, $auth, $namespace);
    }

    /**
     * Registers a POST route
     * 
     * @param callable|array $callback Controller callback
     * @param callable|array $auth Auth controller 
     * @param null|string $namespace Defaults to "rest"
     */
    public static function post(
        string $route,
        $callback,
        $auth,
        ?string $namespace = null
    ): void {
        (new self())->initRoute($route, 'POST', $callback, $auth, $namespace);
    }

    /**
     * Registers a PUT route
     * 
     * @param callable|array $callback Controller callback
     * @param callable|array $auth Auth controller 
     * @param null|string $namespace Defaults to "rest"
     */
    public static function put(
        string $route,
        $callback,
        $auth,
        ?string $namespace = null
    ): void {
        (new self())->initRoute($route, 'PUT', $callback, $auth, $namespace);
    }

    /**
     * Registers a PATCH route
     * 
     * @param callable|array $callback Controller callback
     * @param callable|array $auth Auth controller 
     * @param null|string $namespace Defaults to "rest"
     */
    public static function patch(
        string $route,
        $callback,
        $auth,
        ?string $namespace = null
    ): void {
        (new self())->initRoute($route, 'PATCH', $callback, $auth, $namespace);
    }

    /**
     * Registers a DELETE route
     * 
     * @param callable|array $callback Controller callback
     * @param callable|array $auth Auth controller 
     * @param null|string $namespace Defaults to "rest"
     */
    public static function delete(
        string $route,
        $callback,
        $auth,
        ?string $namespace = null
    ): void {
        (new self())->initRoute($route, 'DELETE', $callback, $auth, $namespace);
    }

    /**
     * Registers the given route
     * 
     * @param callable|array $callback Controller callback
     * @param callable|array $auth Auth controller 
     * @param null|string $namespace Defaults to "rest"
     * @internal Can only be called by the "rest_api_init" hook
     * 
     * @throws \BadMethodCallException If called outside the scope of the
     *                                 "rest_api_init" hook
     */
    public function registerRoute(
        string $route,
        string $method,
        $callback,
        $authCallback,
        ?string $namespace
    ): void {
        if (!doing_action('rest_api_init')) {
            throw new \BadMethodCallException(
                'Routes can\'t be registed outside the "rest_api_init" action'
            );
        }

        register_rest_route(
            $namespace ?? $this->defaultNamespace,
            $this->parseRoute($route),
            [
                'methods'  => $method,
                'callback' => $this->initCallback($callback),
                'permission_callback' => $this->initCallback($authCallback)
            ]
        );
    }

    /**
     * Initiates the objects for class method callbacks
     * 
     * @param callable $callback You can pass non-static methods with the static 
     *                           syntax as well, they'll be initiated in here.
     */
    private function initCallback($callback): callable
    {
        $isStatic = is_array($callback)
            && count($callback) === 2
            && (new Method($callback[0], $callback[1]))->isStatic();

        $class = fn () => $isStatic
            ? $callback[0]
            : new $callback[0];

        return is_array($callback)
            ? [$class(), $callback[1]]
            : $callback;
    }

    /**
     * Parses the URI params in route and replaces them with valid regex 
     * patterns accordingly to WP REST API's rules, capturing group names will
     * be present in the parsed pattern.
     * 
     * @example The route: `/users/{userID}/posts`
     *          would become: `/users/(?P<{userID>[\w]+)/posts`
     */
    private function parseRoute(string $route): string
    {
        return preg_replace_callback(
            '/\{(.*?)\}/',
            fn ($matches) => "(?P<{$matches[1]}>[\w]+)",
            $route
        );
    }

    /**
     * Hooks an action to "rest_api_init" hook for the given route
     */
    private function initRoute(...$args): void
    {
        add_action('rest_api_init', function () use ($args) {
            $this->registerRoute(...$args);
        });
    }
}
