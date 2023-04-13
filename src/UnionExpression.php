<?php

declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder;

use Doctrine\DBAL\Query\QueryBuilder;
use Somnambulist\Components\Collection\MutableCollection as Collection;
use Somnambulist\Components\CTEBuilder\Expression;

final class UnionExpression extends Expression
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        private Expression $firstExpression,
        private Expression $secondExpression,
        string $alias,
        QueryBuilder $query,
        array $dependencies,
    ) {
        parent::__construct($alias, $query, $dependencies);
    }

    public function getSQL(): string
    {
        return sprintf("%s \n\n UNION \n\n %s", $this->firstExpression->getSQL(), $this->secondExpression->getSQL());
    }

    public function getInlineSQL(): string
    {
        return $this->getSQL();
    }

    public function getParameters(): Collection
    {
        return new Collection(
            [
                ...$this->firstExpression->getParameters(),
                ...$this->secondExpression->getParameters(),
            ],
        );
    }
}
