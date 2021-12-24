<?php

declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Somnambulist\Components\Collection\MutableCollection as Collection;
use Somnambulist\Components\CTEBuilder\Expression;

/**
 * Class ExpressionTest
 *
 * @package    Somnambulist\Components\CTEBuilder\Tests
 * @subpackage Somnambulist\Components\CTEBuilder\Tests\ExpressionTest
 */
class ExpressionTest extends TestCase
{

    public function testCreate()
    {
        $cte = new Expression('alias', $q = new QueryBuilder($this->createMock(Connection::class)), ['this', 'that']);

        $this->assertEquals('alias', $cte->getAlias());
        $this->assertEquals(['this', 'that'], $cte->getDependencies());
        $this->assertSame($q, $cte->query());
        $this->assertInstanceOf(Collection::class, $cte->getParameters());
    }

    public function testAddDependencies()
    {
        $cte = new Expression('alias', $q = new QueryBuilder($this->createMock(Connection::class)), ['this', 'that']);

        $this->assertEquals(['this', 'that'], $cte->getDependencies());

        $cte->dependsOn('bob');

        $this->assertEquals(['this', 'that', 'bob'], $cte->getDependencies());
    }

    public function testMethodPassThrough()
    {
        $conn = new Connection(['url' => 'sqlite:///memory'], new Driver());

        $cte = new Expression('alias', $q = new QueryBuilder($conn), ['this', 'that']);
        $cte
            ->select('field', 'field2')
            ->from('table')
            ->orderBy('field2', 'ASC')
        ;

        $this->assertInstanceOf(ExpressionBuilder::class, $cte->expr());
        $this->assertEquals('SELECT field, field2 FROM table ORDER BY field2 ASC', $cte->getSQL());
    }
}
