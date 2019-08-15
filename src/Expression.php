<?php

declare(strict_types=1);

namespace Somnambulist\CTEBuilder;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Somnambulist\Collection\MutableCollection as Collection;
use Somnambulist\CTEBuilder\Behaviours\CanPassThroughToQuery;

/**
 * Class Expression
 *
 * Encapsulates a query builder that will be used as a Common Table Expression (CTE).
 * A CTE must have an alias and can have optional dependencies on other CTEs. When
 * built the dependencies will be resolved and ordered appropriately.
 *
 * During building the CTE, additional dependencies can be added, however the initial
 * dependencies cannot be removed. Similarly: the alias must not be changed once set
 * otherwise other CTEs will fail if they depend on it.
 *
 * CTEs can have parameters bound to them independently of each other; however all
 * parameters must be bound using named place-holders and not positional place-holders.
 * All parameters will be collected into a single parameters Collection at build time.
 *
 * @package    Somnambulist\CTEBuilder
 * @subpackage Somnambulist\CTEBuilder\Expression
 *
 * @method Expression addGroupBy(string $groupBy)
 * @method Expression addOrderBy(string $sort, string $order = null)
 * @method Expression addSelect(string ...$select = null)
 * @method Expression andHaving($having)
 * @method Expression andWhere($where)
 * @method Expression createNamedParameter($value, int $type = ParameterType::STRING, string $placeHolder = null)
 * @method Expression createPositionalParameter($value, int $type = ParameterType::STRING)
 * @method Expression from(string $table, string $alias = null)
 * @method Expression groupBy($groupBy)
 * @method Expression having($having)
 * @method Expression innerJoin(string $fromAlias, string $join, string $alias, $conditions)
 * @method Expression join(string $fromAlias, string $join, string $alias, $conditions)
 * @method Expression leftJoin(string $fromAlias, string $join, string $alias, $conditions)
 * @method Expression orderBy(string $sort, string $order = null)
 * @method Expression orHaving($having)
 * @method Expression orWhere($where)
 * @method Expression rightJoin(string $fromAlias, string $join, string $alias, $conditions)
 * @method Expression select(string ...$field)
 * @method Expression setFirstResult(int $first)
 * @method Expression setMaxResults(int $max)
 * @method Expression setParameter(string|int $key, mixed $value, $type = null)
 * @method Expression setParameters(array $parameters)
 * @method Expression where($where)
 * @method ExpressionBuilder expr()
 */
class Expression
{

    use CanPassThroughToQuery;

    /**
     * @var string
     */
    private $alias;

    /**
     * @var QueryBuilder
     */
    private $query;

    /**
     * @var array
     */
    private $dependencies = [];

    /**
     * Constructor.
     *
     * @param string       $alias
     * @param QueryBuilder $query
     * @param array        $dependencies
     */
    public function __construct(string $alias, QueryBuilder $query, array $dependencies = [])
    {
        $this->alias        = $alias;
        $this->query        = $query;
        $this->dependencies = $dependencies;
    }

    public function __toString()
    {
        return $this->getSQL();
    }

    public function query(): QueryBuilder
    {
        return $this->query;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getSQL(): string
    {
        return $this->query->getSQL();
    }

    public function getParameters(): Collection
    {
        return new Collection($this->query->getParameters());
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function dependsOn(string ...$dependency): self
    {
        $this->dependencies = array_merge($this->dependencies, $dependency);

        return $this;
    }
}
