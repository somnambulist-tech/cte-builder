<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Somnambulist\Components\Collection\MutableCollection as Collection;
use Somnambulist\Components\CTEBuilder\Behaviours\CanPassThroughToQuery;
use Somnambulist\Components\CTEBuilder\Exceptions\CannotCreateUnionWithOrderByException;
use function array_merge;
use function array_walk;
use function count;
use function implode;
use function sprintf;
use function str_contains;

/**
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

    private QueryBuilder $query;
    private string $alias;
    private array $dependencies;
    private array $fields = [];
    private array $unions = [];

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

    public function __clone()
    {
        $this->query = clone $this->query;
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
        $sql = $this->query->getSQL();

        if (count($this->unions) > 0) {
            if (str_contains($sql, 'ORDER BY')) {
                throw CannotCreateUnionWithOrderByException::new();
            }

            foreach ($this->unions as $expr) {
                $sql .= sprintf(' UNION %s%s', $expr['all'] ? 'ALL ' : '', $expr['expr']->getSQL());
            }
        }

        return $sql;
    }

    public function getInlineSQL(): string
    {
        $fields = '';
        if (!empty($this->getFields())) {
            $fields = sprintf(' (%s)', implode(', ', $this->getFields()));
        }

        return sprintf('%s%s AS (%s)', $this->getAlias(), $fields, $this->getSQL());
    }

    public function getParameters(): Collection
    {
        return new Collection($this->query->getParameters());
    }

    /**
     * @return string[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function mergeParameters(array $parameters): void
    {
        $this->setParameters(array_merge($this->query->getParameters(), $parameters));
    }

    public function dependsOn(string ...$dependency): self
    {
        $this->dependencies = array_merge($this->dependencies, $dependency);

        return $this;
    }

    /**
     * Specify the fields that will be returned from this CTE
     *
     * @param string ...$fields
     *
     * @return $this
     */
    public function withFields(string ...$fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Create UNION of all passed expressions, resets the current set of unions
     *
     * @param Expression ...$expr
     *
     * @return $this
     */
    public function union(Expression ...$expr): self
    {
        $this->unions = [];

        array_walk($expr, fn ($ex) => $this->addUnion($ex));

        return $this;
    }

    /**
     * Add an expression to be union'd to this query, if `$all` is true, use UNION ALL
     *
     * @param Expression $expr
     * @param bool $all
     *
     * @return $this
     */
    public function addUnion(Expression $expr, bool $all = false): self
    {
        $this->mergeParameters($expr->getParameters()->all());
        $this->unions[] = ['expr' => $expr, 'all' => $all];

        return $this;
    }

    /**
     * Create UNION ALL for all passed expressions, resets the current set of unions
     *
     * @param Expression ...$expr
     *
     * @return $this
     */
    public function unionAll(Expression ...$expr): self
    {
        $this->unions = [];

        array_walk($expr, fn ($ex) => $this->addUnion($ex, true));

        return $this;
    }
}
