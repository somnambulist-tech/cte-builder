<?php

declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder;

use Doctrine\DBAL\Query\QueryBuilder;
use Somnambulist\Components\Collection\MutableCollection as Collection;
use Somnambulist\Components\CTEBuilder\Expression;

final class WrappedCTE extends Expression
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        private Expression $expression,
        string $alias,
        QueryBuilder $query,
        array $dependencies,
    ) {
        parent::__construct($alias, $query, $dependencies);
    }

    public function getInlineSQL(): string
    {
        return sprintf('%s AS (%s)', $this->getAlias(), $this->expression->getSQL());
    }

    public function getParameters(): Collection
    {
        return $this->expression->getParameters();
    }
}
