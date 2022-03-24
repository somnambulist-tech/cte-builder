<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Pagination;

use Pagerfanta\Adapter\AdapterInterface;
use Somnambulist\Components\CTEBuilder\ExpressionBuilder;

/**
 * Class PagerfantaAdapter
 *
 * @package    Somnambulist\Components\CTEBuilder\Pagination
 * @subpackage Somnambulist\Components\CTEBuilder\Pagination\PagerfantaAdapter
 */
class PagerfantaAdapter implements AdapterInterface
{
    /**
     * @var callable
     */
    private $counter;

    public function __construct(private ExpressionBuilder $cte, callable $counter)
    {
        $this->counter = $counter;
    }

    public function getNbResults(): int
    {
        return (int) $this->prepareCountQueryBuilder()->execute()->fetchOne();
    }

    public function getSlice(int $offset, int $length): iterable
    {
        $qb = clone $this->cte;

        return $qb->setMaxResults($length)->setFirstResult($offset)->execute()->fetchAllAssociative();
    }

    private function prepareCountQueryBuilder(): ExpressionBuilder
    {
        $qb = clone $this->cte;
        $callable = $this->counter;

        $callable($qb);

        return $qb;
    }
}
