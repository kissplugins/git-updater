<?php
/**
 * Dependency injection container for Git Updater FSM.
 *
 * @package Git_Updater
 */

namespace Fragen\Git_Updater;

use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Simple dependency injection container.
 * 
 * Adapted from KISS SBI's Container for Git Updater integration.
 * Provides singleton pattern and automatic dependency resolution.
 */
class Container {
    /**
     * Container bindings.
     *
     * @var array<string, callable|object>
     */
    private array $bindings = [];

    /**
     * Singleton instances.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Register a singleton binding.
     *
     * @param string $abstract Class name or interface.
     * @param callable|null $concrete Factory function or null for auto-resolution.
     * @throws Exception If binding fails.
     */
    public function singleton(string $abstract, ?callable $concrete = null): void {
        $this->bindings[$abstract] = $concrete ?? function() use ($abstract) {
            return $this->build($abstract);
        };
    }

    /**
     * Register a regular binding.
     *
     * @param string $abstract Class name or interface.
     * @param callable|null $concrete Factory function or null for auto-resolution.
     * @throws Exception If binding fails.
     */
    public function bind(string $abstract, ?callable $concrete = null): void {
        $this->bindings[$abstract] = $concrete ?? function() use ($abstract) {
            return $this->build($abstract);
        };
    }

    /**
     * Resolve a binding from the container.
     *
     * @param string $abstract Class name or interface.
     * @return mixed Resolved instance.
     * @throws Exception If resolution fails.
     */
    public function get(string $abstract) {
        // Return existing singleton instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check if we have a binding
        if (!isset($this->bindings[$abstract])) {
            // Try auto-resolution for classes
            if (class_exists($abstract)) {
                $this->singleton($abstract);
            } else {
                throw new Exception("No binding found for {$abstract}");
            }
        }

        // Resolve the binding
        $concrete = $this->bindings[$abstract];
        $instance = is_callable($concrete) ? $concrete($this) : $concrete;

        // Store as singleton if it was registered as one
        if (isset($this->bindings[$abstract])) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build a class instance with dependency injection.
     *
     * @param string $class Class name.
     * @return object Built instance.
     * @throws Exception If building fails.
     */
    private function build(string $class): object {
        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new Exception("Class {$class} does not exist: " . $e->getMessage());
        }

        if (!$reflection->isInstantiable()) {
            throw new Exception("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        // No constructor, just instantiate
        if (!$constructor) {
            return new $class();
        }

        // Resolve constructor dependencies
        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (!$type || $type->isBuiltin()) {
                // Handle primitive types or no type hint
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve primitive parameter {$parameter->getName()} for {$class}");
                }
            } else {
                // Resolve class dependency
                $dependencyClass = $type->getName();
                $dependencies[] = $this->get($dependencyClass);
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Check if a binding exists.
     *
     * @param string $abstract Class name or interface.
     * @return bool True if binding exists.
     */
    public function has(string $abstract): bool {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Remove a binding.
     *
     * @param string $abstract Class name or interface.
     */
    public function forget(string $abstract): void {
        unset($this->bindings[$abstract], $this->instances[$abstract]);
    }

    /**
     * Get all registered bindings.
     *
     * @return array<string, callable|object> All bindings.
     */
    public function getBindings(): array {
        return $this->bindings;
    }

    /**
     * Get all singleton instances.
     *
     * @return array<string, object> All instances.
     */
    public function getInstances(): array {
        return $this->instances;
    }

    /**
     * Clear all bindings and instances.
     */
    public function flush(): void {
        $this->bindings = [];
        $this->instances = [];
    }

    /**
     * Register a shared instance.
     *
     * @param string $abstract Class name or interface.
     * @param object $instance Instance to share.
     */
    public function instance(string $abstract, object $instance): void {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Call a method with dependency injection.
     *
     * @param callable $callback Callback to call.
     * @param array $parameters Additional parameters.
     * @return mixed Callback result.
     * @throws Exception If method call fails.
     */
    public function call(callable $callback, array $parameters = []) {
        if (is_array($callback)) {
            [$class, $method] = $callback;
            
            if (is_string($class)) {
                $class = $this->get($class);
            }
            
            $reflection = new ReflectionClass($class);
            $methodReflection = $reflection->getMethod($method);
        } else {
            $methodReflection = new \ReflectionFunction($callback);
        }

        $dependencies = [];
        foreach ($methodReflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
            } elseif ($parameter->getType() && !$parameter->getType()->isBuiltin()) {
                $dependencies[] = $this->get($parameter->getType()->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new Exception("Cannot resolve parameter {$name}");
            }
        }

        return call_user_func_array($callback, $dependencies);
    }
}
