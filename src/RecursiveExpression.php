<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class RecursiveExpression
 *
 * Allows defining a recursive common table expression.
 *
 * @package    Somnambulist\Components\CTEBuilder
 * @subpackage Somnambulist\Components\CTEBuilder\RecursiveExpression
 */
class RecursiveExpression extends Expression
{

    private string $initialSelect = '';
    private bool $uniqueRows = false;

    /**
     * By default the recursive CTE will be made with UNION ALL, set to true for a UNION query
     *
     * @param bool $union
     *
     * @return $this
     */
    public function withUniqueRows(bool $union = false): self
    {
        $this->uniqueRows = $union;

        return $this;
    }

    /**
     * This is the initial select clause that is used to seed the recursive call
     *
     * This can be a QueryBuilder instance (including parameters that MUST be named parameters)
     * or an SQL string such as `SELECT 1` or `VALUES(`)` etc.
     *
     * @param string|QueryBuilder $query
     *
     * @return $this
     */
    public function withInitialSelect(string|QueryBuilder $query): self
    {
        if ($query instanceof QueryBuilder) {
            $this->mergeParameters($query->getParameters());
            $query = $query->getSQL();
        }

        $this->initialSelect = $query;

        return $this;
    }

    public function getInitialSelect(): string
    {
        return $this->initialSelect;
    }

    public function fetchUniqueRows(): bool
    {
        return $this->uniqueRows;
    }

    public function getInlineSQL(): string
    {
        $fields = '';
        if (!empty($this->getFields())) {
            $fields = sprintf(' (%s)', implode(', ', $this->getFields()));
        }

        return sprintf(
            '%s%s AS (%s %s %s)',
            $this->getAlias(),
            $fields,
            $this->initialSelect,
            $this->uniqueRows ? 'UNION' : 'UNION ALL',
            $this->getSQL()
        );
    }
}
