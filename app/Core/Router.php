<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionMethod;
use ReflectionNamedType;

/**
 * Maps METHOD + path to [Controller::class, 'method'] handlers.
 * Path placeholders: /buses/{id}/edit  -> $id passed to the controller method by name.
 * Middleware strings: "auth", "csrf", "role:admin,super_admin" (alias registered via alias()).
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,handler:array{0:class-string,1:string},middleware:string[]}> */
    private array $routes = [];
    /** @var array<string,class-string<MiddlewareInterface>> */
    private array $aliases = [];

    public function __construct(private Container $container, private Request $request)
    {
    }

    public function alias(string $name, string $middlewareClass): void
    {
        $this->aliases[$name] = $middlewareClass;
    }

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /** State-changing routes MUST use post(); never GET. */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', '/' . trim($path, '/')) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'middleware' => $middleware];
    }

    public function dispatch(): void
    {
        $path = $this->request->path();
        $method = $this->request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route['middleware'] as $spec) {
                $this->runMiddleware($spec);
            }
            $this->invoke($route['handler'], $params);
            return;
        }

        throw new HttpException($pathMatched ? 405 : 404);
    }

    private function runMiddleware(string $spec): void
    {
        [$name, $argString] = array_pad(explode(':', $spec, 2), 2, null);
        if (!isset($this->aliases[$name])) {
            throw new HttpException(500, "Unknown middleware [$name].");
        }
        $args = $argString === null || $argString === '' ? [] : explode(',', $argString);
        /** @var MiddlewareInterface $middleware */
        $middleware = $this->container->get($this->aliases[$name]);
        $middleware->handle($this->request, $args);
    }

    private function invoke(array $handler, array $params): void
    {
        [$class, $action] = $handler;
        $controller = $this->container->get($class);
        $reflection = new ReflectionMethod($controller, $action);

        $args = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $params)) {
                $value = $params[$name];
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && $type->getName() === 'int') {
                    if (!ctype_digit((string) $value)) {
                        throw new HttpException(404);
                    }
                    $value = (int) $value;
                }
                $args[] = $value;
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } else {
                throw new HttpException(500, "Route parameter [$name] missing for $class::$action.");
            }
        }

        $result = $reflection->invokeArgs($controller, $args);
        if (is_string($result)) {
            echo $result;
        }
    }
}
