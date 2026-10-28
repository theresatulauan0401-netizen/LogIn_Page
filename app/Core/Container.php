<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Small dependency-injection container. Registered factories are used first;
 * any other App\... class is auto-wired from its constructor type hints.
 */
final class Container
{
    private static ?Container $current = null;

    /** @var array<string,Closure> */
    private array $factories = [];
    /** @var array<string,object> */
    private array $instances = [];

    public static function setInstance(Container $container): void
    {
        self::$current = $container;
    }

    public static function getInstance(): Container
    {
        return self::$current ??= new self();
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }
        return $this->instances[$id] = $this->build($id);
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve [$class]: class does not exist.");
        }
        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Cannot instantiate [$class].");
        }
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return new $class();
        }
        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($type !== null && $type->allowsNull()) {
                $args[] = null;
            } else {
                throw new RuntimeException("Cannot resolve parameter \${$param->getName()} of [$class].");
            }
        }
        return $ref->newInstanceArgs($args);
    }
}
