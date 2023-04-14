<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Exceptions;

use Assert\Assertion;
use Assert\InvalidArgumentException;

class ExpressionAlreadyExistsException extends InvalidArgumentException
{

    public static function aliasExists(string $alias): self
    {
        return new self(
            sprintf('CTE with alias "%s" already exists in the query builder', $alias),
            Assertion::INVALID_KEY_EXISTS,
            'commonTableExpressions',
            $alias
        );
    }
}
