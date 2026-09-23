<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ReflectionClass;
use ReflectionMethod;

abstract class TestCase extends BaseTestCase
{
    protected function getAccessibleReflectionMethod(Model $model, string $method): ReflectionMethod
    {
        $reflectionProtector = new ReflectionClass($model);

        return $reflectionProtector->getMethod($method);
    }

    /**
     * Allows a test to call a protected method.
     *
     * @param Model $model
     * @param string $methodName
     * @param array $params
     * @return mixed
     */
    protected function runProtectedMethod(Model $model, string $methodName, array $params = []): mixed
    {
        $method = $this->getAccessibleReflectionMethod($model, $methodName);

        return $method->invoke($model, ...$params);
    }
}
