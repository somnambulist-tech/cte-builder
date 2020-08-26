<?php declare(strict_types=1);

namespace Somnambulist\CTEBuilder\Exceptions;

use Assert\Assertion;
use Assert\InvalidArgumentException;

/**
 * Class ExpressionNotFoundException
 *
 * @package    Somnambulist\CTEBuilder\Exceptions
 * @subpackage Somnambulist\CTEBuilder\Exceptions\ExpressionNotFoundException
 */
class ExpressionNotFoundException extends InvalidArgumentException
{

    public static function aliasNotFound(string $alias): self
    {
        return new static(
            sprintf('CTE with alias "%s" could not be found', $alias),
            Assertion::INVALID_KEY_EXISTS,
            'commonTableExpressions',
            $alias
        );
    }
}
