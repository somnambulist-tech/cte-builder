<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Exceptions;

use RuntimeException;

class CannotCreateUnionWithOrderByException extends RuntimeException
{
    public static function new()
    {
        return new self('Expression query object does not support ORDER BY with UNION statements');
    }
}
