<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Behaviours;

use BadMethodCallException;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use function implode;
use function in_array;
use function sprintf;

/**
 * Trait CanPassThroughToQuery
 *
 * Allow passing through certain methods to an underlying QueryBuilder instance.
 *
 * @package    Somnambulist\Components\CTEBuilder\Behaviours
 * @subpackage Somnambulist\Components\CTEBuilder\Behaviours\CanPassThroughToQuery
 *
 * @property QueryBuilder $query
 */
trait CanPassThroughToQuery
{

    /**
     * @return ExpressionBuilder|static
     */
    public function __call(string $name, array $arguments)
    {
        $allowed = [
            'addGroupBy', 'addOrderBy', 'addSelect', 'andHaving', 'andWhere',
            'createNamedParameter', 'createPositionalParameter', 'expr', 'from', 'groupBy', 'having',
            'innerJoin', 'join', 'leftJoin', 'orderBy', 'orHaving', 'orWhere', 'rightJoin', 'select',
            'setFirstResult', 'setMaxResults', 'setParameter', 'setParameters', 'where',
        ];

        if (in_array($name, $allowed)) {
            if (($ret = $this->query->{$name}(...$arguments)) instanceof ExpressionBuilder) {
                return $ret;
            }

            return $this;
        }

        throw new BadMethodCallException(sprintf(
            'Method "%s" is not supported for pass through on "%s"; expected one of (%s)',
            $name, static::class, implode(', ', $allowed)
        ));
    }
}
