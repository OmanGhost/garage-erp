<?php

namespace App\Support\Crud;

class CrudRegistry
{
    /** @var array<string,class-string<CrudResource>> */
    protected static array $resources = [];

    /** @param array<int,class-string<CrudResource>> $classes */
    public static function register(array $classes): void
    {
        foreach ($classes as $class) {
            static::$resources[$class::$slug] = $class;
        }
    }

    public static function resolve(string $slug): CrudResource
    {
        $class = static::$resources[$slug] ?? abort(404);

        return new $class;
    }

    public static function has(string $slug): bool
    {
        return isset(static::$resources[$slug]);
    }

    public static function all(): array
    {
        return static::$resources;
    }
}
