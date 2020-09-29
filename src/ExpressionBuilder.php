<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder as DBALExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use Somnambulist\Collection\MutableCollection as Collection;
use Somnambulist\Components\CTEBuilder\Behaviours\CanPassThroughToQuery;
use Somnambulist\Components\CTEBuilder\Exceptions\ExpressionAlreadyExistsException;
use Somnambulist\Components\CTEBuilder\Exceptions\ExpressionNotFoundException;
use Somnambulist\Components\CTEBuilder\Exceptions\UnresolvableDependencyException;

/**
 * Class ExpressionBuilder
 *
 * Aggregates and executes the Expressions as an SQL query. Requires that a query that
 * uses the CTEs be set.
 *
 * ExpressionBuilder (not to be confused with DBAL\Query\Expression\ExpressionBuilder) allows
 * method pass-through to the underlying primary query builder and any bound CTE can be
 * accessed using property accessors.
 *
 * @package    Somnambulist\Components\CTEBuilder
 * @subpackage Somnambulist\Components\CTEBuilder\ExpressionBuilder
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
 * @method DBALExpressionBuilder expr()
 */
class ExpressionBuilder
{

    use CanPassThroughToQuery;

    private Connection $conn;
    private QueryBuilder $query;
    private Collection $expressions;
    private Collection $parameters;
    private ?LoggerInterface $logger;

    public function __construct(Connection $conn, LoggerInterface $logger = null)
    {
        $this->conn        = $conn;
        $this->logger      = $logger;
        $this->query       = $conn->createQueryBuilder();
        $this->expressions = new Collection();
        $this->parameters  = new Collection();
    }

    public function __toString()
    {
        return $this->getSQL();
    }

    public function __get($name)
    {
        if ($this->has($name)) {
            return $this->get($name);
        }

        throw new OutOfBoundsException(sprintf('CTE with alias "%s" has not been created', $name));
    }

    public function expressions(): Collection
    {
        return $this->expressions;
    }

    public function clear(): void
    {
        $this->query       = $this->conn->createQueryBuilder();
        $this->parameters  = new Collection();
        $this->expressions = new Collection();
    }

    public function query(): QueryBuilder
    {
        return $this->query;
    }

    public function createQuery(): QueryBuilder
    {
        return $this->conn->createQueryBuilder();
    }

    /**
     * Create a new CTE Expression with optionally required dependencies
     *
     * These dependencies are permanent and cannot be removed from the expression.
     *
     * @param string $alias
     * @param string ...$dependsOn A number of fixed dependent WITH expressions
     *
     * @return Expression
     */
    public function createExpression(string $alias, string ...$dependsOn): Expression
    {
        if ($this->has($alias)) {
            throw ExpressionAlreadyExistsException::aliasExists($alias);
        }

        return $this->with(new Expression($alias, $this->conn->createQueryBuilder(), $dependsOn))->get($alias);
    }

    public function with(Expression $cte): self
    {
        $this->expressions->set($cte->getAlias(), $cte);

        return $this;
    }

    public function get(string $alias): Expression
    {
        if (!$this->has($alias)) {
            throw ExpressionNotFoundException::aliasNotFound($alias);
        }

        return $this->expressions->get($alias);
    }

    public function has(string $alias): bool
    {
        return $this->expressions->has($alias);
    }

    public function getParameters(): Collection
    {
        $this->mergeParameters();

        return $this->parameters;
    }

    public function getParameter(string $param)
    {
        $this->mergeParameters();

        return $this->parameters->get($param);
    }

    public function hasParameter(string $param): bool
    {
        $this->mergeParameters();

        return $this->parameters->has($param);
    }

    private function mergeParameters(): void
    {
        $this->parameters->merge($this->query->getParameters());

        $this->expressions->each(function (Expression $cte) {
            $this->parameters->merge($cte->getParameters());
        });
    }

    public function execute(): Statement
    {
        $this->log();

        $stmt = $this->conn->prepare($this->getSQL());

        $this->getParameters()->each(function ($value, $key) use ($stmt) {
            $stmt->bindValue($key, $value, (is_int($value) ? ParameterType::INTEGER : ParameterType::STRING));
        });

        $stmt->execute();

        return $stmt;
    }

    public function getSQL(): string
    {
        return trim(sprintf('%s %s', $this->buildWith(), $this->query->getSQL()));
    }

    private function buildWith(): string
    {
        $with = $this
            ->buildDependencyTree($this->expressions)
            ->map(function (Expression $cte, string $key) {
                return sprintf('%s AS (%s)', $cte->getAlias(), $cte->getSQL());
            })
            ->implode(', ')
        ;

        return $with ? 'WITH ' . $with : '';
    }

    /**
     * @link https://stackoverflow.com/questions/39711720/php-order-array-based-on-elements-dependency
     */
    private function buildDependencyTree(Collection $ctes): Collection
    {
        $sortedExpressions    = new Collection();
        $resolvedDependencies = new Collection();

        while ($ctes->count() > $sortedExpressions->count()) {
            $resolvedDependenciesForCte = false;
            $alias = $dep = 'undefined';

            foreach ($ctes as $alias => $cte) {
                if ($resolvedDependencies->has($alias)) {
                    continue;
                }

                $resolved = true;

                foreach ($cte->getDependencies() as $dep) {
                    if (!is_null($test = $ctes->get($dep)) && in_array($alias, $test->getDependencies())) {
                        throw UnresolvableDependencyException::cyclicalDependency($alias, $dep);
                    }

                    if (!$resolvedDependencies->has($dep)) {
                        $resolved = false;
                        break;
                    }
                }

                if ($resolved) {
                    $resolvedDependencies->set($alias, true);
                    $sortedExpressions->add($cte);
                    $resolvedDependenciesForCte = true;
                }
            }

            if (!$resolvedDependenciesForCte) {
                throw UnresolvableDependencyException::cannotResolve($alias, $dep);
            }
        }

        return $sortedExpressions;
    }

    /**
     * Logs the compiled query to the standard logger as a debug message
     *
     * @codeCoverageIgnore
     */
    private function log()
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->debug($q = $this->expandQueryWithParameterSubstitution($this->getSQL(), $this->getParameters()));
    }

    /**
     * Returns a substituted compiled query for debugging purposes
     *
     * This is intended for debugging the build process and should not be used in production code.
     *
     * @param string     $query
     * @param Collection $parameters
     *
     * @return string
     * @internal
     * @codeCoverageIgnore
     */
    private function expandQueryWithParameterSubstitution(string $query, Collection $parameters)
    {
        $debug = $parameters->map(function ($value) {
            return is_numeric($value) ? $value : $this->conn->quote((string)$value);
        });

        return strtr($query, $debug->toArray());
    }
}
