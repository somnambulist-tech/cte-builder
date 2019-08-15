<?php

declare(strict_types=1);

namespace Somnambulist\CTEBuilder\Exceptions;

use RuntimeException;

/**
 * Class UnresolvableDependencyException
 *
 * @package    Somnambulist\CTEBuilder\Exceptions
 * @subpackage Somnambulist\CTEBuilder\Exceptions\UnresolvableDependencyException
 */
class UnresolvableDependencyException extends RuntimeException
{

    public static function cannotResolve(string $item, string $requires): self
    {
        return new static(
            sprintf('CTE named "%s" depends on "%s" that could not be resolved', $item, $requires)
        );
    }

    public static function cyclicalDependency(string $item, string $requires): self
    {
        return new static(
            sprintf('CTE named "%s" has a cyclical dependency with "%s"', $item, $requires)
        );
    }
}
