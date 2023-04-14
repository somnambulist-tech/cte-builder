<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Exceptions;

use InvalidArgumentException;

class MissingExpressionAliasException extends InvalidArgumentException
{
    public static function new(): self
    {
        return new self('Expression passed to with() does not have an assigned alias');
    }
}
